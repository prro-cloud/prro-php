<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Http;

use PrroCloud\Prro\Exception\TransportException;

/**
 * Транспорт на ext-curl — типовий. Один curl-хендл на транспорт слугує
 * всім запитам, тож з'єднання з API живе між викликами в межах процесу
 * (keep-alive).
 *
 * Хендл не можна ділити між корутинами (Swoole, Octane): створюйте клієнт
 * на корутину або передайте PSR-18 клієнт із пулом через {@see Psr18Transport}.
 */
final class CurlTransport implements Transport
{
    private ?\CurlHandle $handle = null;

    /**
     * @param float                $timeout        межа всього запиту, с; має перевищувати 30 с синхронного
     *                                             очікування ДПС
     * @param float                $connectTimeout межа встановлення з'єднання, с
     * @param string|null          $caBundle       шлях до файла сертифікатів CA — коли системний набір на
     *                                             хостингу застарів
     * @param string|null          $proxy          проксі: "http://user:pass@host:3128"
     * @param array<int, mixed>    $curlOptions    додаткові опції curl_setopt — запасний вихід; мають
     *                                             пріоритет над рештою
     */
    public function __construct(
        private readonly float $timeout = 60.0,
        private readonly float $connectTimeout = 10.0,
        private readonly ?string $caBundle = null,
        private readonly ?string $proxy = null,
        private readonly array $curlOptions = [],
    ) {}

    public function send(Request $request): Response
    {
        $ch = $this->handle ??= $this->init();
        curl_reset($ch);

        $headers = [];
        foreach ($request->headers as $name => $value) {
            $headers[] = $name . ': ' . $value;
        }
        // Без цього curl додає «Expect: 100-continue» до великих тіл і чекає
        // зайвий раунд.
        $headers[] = 'Expect:';

        $responseHeaders = [];
        $options = [
            CURLOPT_URL => $request->url,
            CURLOPT_CUSTOMREQUEST => $request->method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_TIMEOUT_MS => (int) round($this->timeout * 1000),
            CURLOPT_CONNECTTIMEOUT_MS => (int) round($this->connectTimeout * 1000),
            CURLOPT_ENCODING => '',
            CURLOPT_HEADERFUNCTION => static function (\CurlHandle $ch, string $line) use (&$responseHeaders): int {
                // Новий рядок статусу (після 100 Continue чи редиректу) скидає
                // заголовки попередньої відповіді.
                if (str_starts_with($line, 'HTTP/')) {
                    $responseHeaders = [];
                } elseif (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $responseHeaders[strtolower(trim($name))][] = trim($value);
                }

                return strlen($line);
            },
        ];
        if ($request->method === 'HEAD') {
            $options[CURLOPT_NOBODY] = true;
        }
        if ($request->body !== null) {
            $options[CURLOPT_POSTFIELDS] = $request->body;
        }
        if ($this->caBundle !== null) {
            $options[CURLOPT_CAINFO] = $this->caBundle;
        }
        if ($this->proxy !== null) {
            $options[CURLOPT_PROXY] = $this->proxy;
        }
        if (!curl_setopt_array($ch, array_replace($options, $this->curlOptions))) {
            throw new TransportException('curl: invalid option: ' . curl_error($ch));
        }

        $body = curl_exec($ch);
        if (!is_string($body)) {
            throw new TransportException(sprintf('curl error %d: %s', curl_errno($ch), curl_error($ch)));
        }

        /** @var array<string, list<string>> $responseHeaders */
        return new Response(curl_getinfo($ch, CURLINFO_RESPONSE_CODE), $responseHeaders, $body);
    }

    private function init(): \CurlHandle
    {
        $ch = curl_init();
        if ($ch === false) {
            throw new TransportException('curl: init failed');
        }

        return $ch;
    }
}
