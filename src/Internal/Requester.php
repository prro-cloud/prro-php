<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Internal;

use PrroCloud\Prro\Exception\ApiException;
use PrroCloud\Prro\Exception\DecodeException;
use PrroCloud\Prro\Exception\InvalidArgumentException;
use PrroCloud\Prro\Exception\TransportException;
use PrroCloud\Prro\Http\Request;
use PrroCloud\Prro\Http\Transport;
use Psr\Log\LoggerInterface;

/**
 * Конвеєр запиту: заголовки, ключ ідемпотентності, повтори з паузою,
 * розбір відповіді й помилок.
 *
 * @internal
 */
final class Requester
{
    private const RETRYABLE_STATUSES = [429, 500, 502, 503, 504];

    public function __construct(
        private readonly string $token,
        private readonly string $baseUrl,
        private readonly string $userAgent,
        private readonly ?string $language,
        private readonly int $maxRetries,
        private readonly float $retryMin,
        private readonly float $retryMax,
        private readonly Transport $transport,
        private readonly Clock $clock,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    /**
     * Підставляє в format екрановані ідентифікатори; порожній ідентифікатор —
     * помилка, а не запит до сусіднього маршруту.
     */
    public static function path(string $format, string ...$ids): string
    {
        foreach ($ids as $id) {
            if ($id === '') {
                throw new InvalidArgumentException('prro: empty id');
            }
        }

        return vsprintf($format, array_map(rawurlencode(...), $ids));
    }

    /**
     * Виконує запит із повторами і повертає розібране тіло успішної
     * відповіді; null — відповідь 204 або decode = false.
     *
     * @param array<string, string> $query
     * @param bool                  $fiscal операція вимагає Idempotency-Key і приймає ?mode=
     *
     * @return array<mixed>|null
     *
     * @throws ApiException       відповідь не-2xx
     * @throws TransportException мережевий збій після всіх повторів
     * @throws DecodeException    успішну відповідь не розібрано
     */
    public function send(
        string $method,
        string $path,
        array $query = [],
        mixed $body = null,
        bool $fiscal = false,
        ?string $idempotencyKey = null,
        bool $async = false,
        bool $decode = true,
    ): ?array {
        // Порожній масив — порожній об'єкт: PATCH без змін надсилає {}, а не [].
        $payload = $body === null ? null : Json::encode($body === [] ? new \stdClass() : $body);

        $key = null;
        if ($fiscal) {
            $key = $idempotencyKey !== null && $idempotencyKey !== '' ? $idempotencyKey : Uuid::v7();
            if ($async) {
                $query['mode'] = 'async';
            }
        }
        $url = $this->baseUrl . $path;
        if ($query !== []) {
            ksort($query);
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }
        $request = new Request($method, $url, $this->headers($payload !== null, $key), $payload);

        // Повторювати безпечно лише те, що не може виконатися двічі: GET і
        // PUT ідемпотентні за семантикою HTTP, фіскальні операції — за ключем.
        $retryable = $method === 'GET' || $method === 'PUT' || $key !== null;

        for ($attempt = 0; ; $attempt++) {
            $start = $this->clock->now();
            $canRetry = $retryable && $attempt < $this->maxRetries;
            try {
                $response = $this->transport->send($request);
            } catch (TransportException $e) {
                $this->log('prro: request failed', $method, $path, $attempt, null, $start, $e);
                if (!$canRetry) {
                    throw new TransportException(sprintf('prro: %s %s: %s', $method, $path, $e->getMessage()), 0, $e);
                }
                $this->clock->sleep(Backoff::delay($attempt, null, $this->retryMin, $this->retryMax));
                continue;
            }
            $this->log('prro: request', $method, $path, $attempt, $response->status, $start, null);

            if ($canRetry && in_array($response->status, self::RETRYABLE_STATUSES, true)) {
                $this->clock->sleep(Backoff::delay($attempt, $response->header('Retry-After'), $this->retryMin, $this->retryMax));
                continue;
            }
            if ($response->status < 200 || $response->status > 299) {
                throw ApiException::fromResponse($response->status, $response->body);
            }
            if (!$decode || $response->status === 204) {
                return null;
            }

            return Json::decodeObject($response->body);
        }
    }

    /**
     * @return array<string, string>
     */
    private function headers(bool $hasBody, ?string $key): array
    {
        $headers = [
            'Accept' => 'application/json',
            'User-Agent' => $this->userAgent,
        ];
        if ($this->token !== '') {
            $headers['Authorization'] = 'Bearer ' . $this->token;
        }
        if ($hasBody) {
            $headers['Content-Type'] = 'application/json';
        }
        if ($key !== null) {
            $headers['Idempotency-Key'] = $key;
        }
        if ($this->language !== null && $this->language !== '') {
            $headers['Accept-Language'] = $this->language;
        }

        return $headers;
    }

    private function log(string $message, string $method, string $path, int $attempt, ?int $status, float $start, ?\Throwable $error): void
    {
        if ($this->logger === null) {
            return;
        }
        $context = [
            'method' => $method,
            'path' => $path,
            'attempt' => $attempt + 1,
            'duration_ms' => (int) round(($this->clock->now() - $start) * 1000),
        ];
        if ($status !== null) {
            $context['status'] = $status;
        }
        if ($error !== null) {
            $context['error'] = $error->getMessage();
        }
        $this->logger->debug($message, $context);
    }
}
