<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Webhook;

use PrroCloud\Prro\Exception\DecodeException;
use PrroCloud\Prro\Internal\Hydrator;
use PrroCloud\Prro\Internal\Json;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Приймач вебхуків PRRO.cloud: перевірка підпису, розбір конверта доставки
 * і готовий обробник.
 *
 * Сервіс надсилає кожну подію POST-запитом із тілом {delivery_id, event,
 * attempt, sequence, occurred_at, data} і заголовком
 * X-Signature: "sha256=<hex>" — HMAC-SHA256 тіла на секреті вебхука.
 * Секрет видають рівно один раз, коли вебхук створюють: у кабінеті або
 * через {@see \PrroCloud\Prro\Service\WebhooksService::create()}.
 *
 * Підпис перевіряється на сирих байтах тіла: передавайте тіло так, як його
 * отримав сервер (php://input, $request->getContent() у Laravel і Symfony,
 * $request->get_body() у WordPress), а не перекодований JSON чи $_POST.
 *
 * Доставка гарантована «щонайменше один раз»: та сама подія може прийти
 * повторно з тим самим {@see Event::$deliveryId} — відкидайте повтори за
 * ним. Подія, яку приймач не прийняв, може прийти пізніше за наступні події
 * тієї самої каси: упорядковуйте їх за {@see Event::$sequence}.
 */
final class Webhook
{
    /** Заголовок із підписом доставки. */
    public const SIGNATURE_HEADER = 'X-Signature';

    /**
     * Найбільше тіло доставки. Подія register.offline_abandoned перелічує
     * всі документи сесії, тож межа має запас.
     */
    public const MAX_BODY_BYTES = 10 * 1024 * 1024;

    private const PREFIX = 'sha256=';

    /**
     * Повертає значення заголовка X-Signature для тіла body — так його
     * рахує сервіс. Знадобиться для тестів власного приймача.
     */
    public static function sign(string $secret, string $body): string
    {
        return self::PREFIX . hash_hmac('sha256', $body, $secret);
    }

    /**
     * Перевіряє підпис signature тіла body за сталий час. Перевіряйте
     * підпис до будь-якої обробки тіла.
     *
     * @throws InvalidSignatureException
     */
    public static function verify(string $secret, string $body, string $signature): void
    {
        if (!str_starts_with($signature, self::PREFIX)) {
            throw new InvalidSignatureException('webhook: invalid signature');
        }
        $hex = substr($signature, strlen(self::PREFIX));
        $sum = strlen($hex) % 2 === 0 && ctype_xdigit($hex) ? hex2bin($hex) : false;
        if ($sum === false || !hash_equals(hash_hmac('sha256', $body, $secret, true), $sum)) {
            throw new InvalidSignatureException('webhook: invalid signature');
        }
    }

    /**
     * Перевіряє підпис і розбирає тіло доставки.
     *
     * @throws InvalidSignatureException
     * @throws WebhookException          конверт не розібрано
     */
    public static function parse(string $secret, string $body, string $signature): Event
    {
        self::verify($secret, $body, $signature);
        try {
            return Hydrator::hydrate(Event::class, Json::decodeObject($body, 'envelope'));
        } catch (DecodeException $e) {
            throw new WebhookException('webhook: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Читає поточний запит чистого PHP: тіло з php://input (не більше
     * MAX_BODY_BYTES) і підпис із заголовка X-Signature.
     *
     * @throws PayloadTooLargeException
     * @throws InvalidSignatureException
     * @throws WebhookException
     */
    public static function fromGlobals(string $secret): Event
    {
        return self::parse($secret, self::readBody(self::readInput()), self::globalSignature());
    }

    /**
     * Читає PSR-7 запит (Slim, Mezzio, Symfony через PSR-міст тощо).
     *
     * @throws PayloadTooLargeException
     * @throws InvalidSignatureException
     * @throws WebhookException
     */
    public static function fromPsrRequest(ServerRequestInterface $request, string $secret): Event
    {
        $stream = $request->getBody();
        if ($stream->isSeekable()) {
            $stream->rewind();
        }
        $body = '';
        while (!$stream->eof() && strlen($body) <= self::MAX_BODY_BYTES) {
            $chunk = $stream->read(self::MAX_BODY_BYTES + 1 - strlen($body));
            if ($chunk === '') {
                break;
            }
            $body .= $chunk;
        }

        return self::parse($secret, self::readBody($body), $request->getHeaderLine(self::SIGNATURE_HEADER));
    }

    /**
     * Приймає доставку поточного запиту чистого PHP і надсилає відповідь:
     * перевіряє підпис, розбирає конверт і викликає handler.
     *
     *     Webhook::handle(getenv('PRRO_WEBHOOK_SECRET'), function (Event $e): void {
     *         if ($e->eventType() === WebhookEvent::TaskCompleted) {
     *             markOrder($e->task()->taskId);
     *         }
     *     });
     *
     * Виняток із handler означає «не оброблено»: приймач відповідає 500, і
     * сервіс повторить доставку пізніше. Сервіс вважає успіхом будь-яку
     * 2xx-відповідь і повторює решту, тож handler має бути ідемпотентним
     * щодо $e->deliveryId. Коди відповіді — як у {@see HandlerResult}.
     *
     * @param callable(Event): mixed $handler
     */
    public static function handle(string $secret, callable $handler): HandlerResult
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $result = self::process(
            $secret,
            $handler,
            is_string($method) ? $method : 'GET',
            $method === 'POST' ? self::readInput() : '',
            self::globalSignature(),
        );

        if (!headers_sent()) {
            http_response_code($result->status);
            header('Content-Type: text/plain; charset=utf-8');
            foreach ($result->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }
        echo $result->body;

        return $result;
    }

    /**
     * Обробляє доставку і повертає відповідь, нічого не надсилаючи, — для
     * фреймворків, які формують відповідь самі.
     *
     *     $r = Webhook::process($secret, $handler, $request->method(), $request->getContent(),
     *         (string) $request->header('X-Signature'));
     *     return response($r->body, $r->status, $r->headers);
     *
     * @param callable(Event): mixed $handler
     */
    public static function process(string $secret, callable $handler, string $method, string $body, string $signature): HandlerResult
    {
        if (strtoupper($method) !== 'POST') {
            return new HandlerResult(405, 'method not allowed', ['Allow' => 'POST']);
        }
        try {
            $event = self::parse($secret, self::readBody($body), $signature);
        } catch (InvalidSignatureException $e) {
            return new HandlerResult(401, 'invalid signature', error: $e);
        } catch (PayloadTooLargeException $e) {
            return new HandlerResult(413, 'body too large', error: $e);
        } catch (WebhookException $e) {
            return new HandlerResult(400, 'bad request', error: $e);
        }
        try {
            $handler($event);
        } catch (\Throwable $e) {
            return new HandlerResult(500, 'handler failed', error: $e);
        }

        return new HandlerResult(200, 'ok');
    }

    private static function readBody(string $body): string
    {
        if (strlen($body) > self::MAX_BODY_BYTES) {
            throw new PayloadTooLargeException(sprintf('webhook: body exceeds %d bytes', self::MAX_BODY_BYTES));
        }

        return $body;
    }

    private static function readInput(): string
    {
        $in = fopen('php://input', 'rb');
        if ($in === false) {
            return '';
        }
        $body = stream_get_contents($in, self::MAX_BODY_BYTES + 1);
        fclose($in);

        return $body === false ? '' : $body;
    }

    private static function globalSignature(): string
    {
        $sig = $_SERVER['HTTP_X_SIGNATURE'] ?? '';

        return is_string($sig) ? $sig : '';
    }
}
