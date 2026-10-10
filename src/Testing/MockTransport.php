<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Testing;

use PrroCloud\Prro\Exception\TransportException;
use PrroCloud\Prro\Http\Request;
use PrroCloud\Prro\Http\Response;
use PrroCloud\Prro\Http\Transport;

/**
 * Транспорт для тестів вашої інтеграції: віддає заздалегідь задані
 * відповіді і запам'ятовує запити.
 *
 *     $mock = new MockTransport(
 *         MockTransport::json(201, ['task_id' => 't1', 'type' => 'receipt', 'status' => 'succeeded']),
 *     );
 *     $client = new Client(token: 'test', transport: $mock);
 *     $client->receipts->create($receipt);
 *     $mock->lastRequest()->header('Idempotency-Key');
 *
 * Елементи черги: Response — відповідь; \Throwable — кидається (наприклад,
 * TransportException для мережевого збою); \Closure(Request): Response —
 * обчислює відповідь. Кожен елемент спрацьовує один раз; коли черга
 * порожня, діє відповідь, задана через {@see repeat()}, інакше —
 * \OutOfRangeException.
 */
final class MockTransport implements Transport
{
    /** @var list<Response|\Throwable|\Closure(Request): Response> */
    private array $queue = [];

    /** @var (Response|\Closure(Request): Response)|null */
    private Response|\Closure|null $fallback = null;

    /** @var list<Request> */
    private array $requests = [];

    /**
     * @param Response|\Throwable|\Closure(Request): Response ...$responses
     */
    public function __construct(Response|\Throwable|\Closure ...$responses)
    {
        $this->push(...$responses);
    }

    /**
     * Транспорт, що на кожен запит відповідає response.
     *
     * @param Response|\Closure(Request): Response $response
     */
    public static function repeat(Response|\Closure $response): self
    {
        $mock = new self();
        $mock->fallback = $response;

        return $mock;
    }

    /**
     * Відповідь із JSON-тілом: масив кодується, рядок передається як є.
     *
     * @param array<mixed>|string                $body
     * @param array<string, string|list<string>> $headers
     */
    public static function json(int $status, array|string $body, array $headers = []): Response
    {
        $json = is_string($body) ? $body : json_encode($body === [] ? new \stdClass() : $body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return new Response($status, ['Content-Type' => 'application/json'] + $headers, $json);
    }

    /**
     * Мережевий збій: транспорт не отримав відповіді.
     */
    public static function networkError(string $message = 'connection reset by peer'): TransportException
    {
        return new TransportException($message);
    }

    /**
     * Додає відповіді в кінець черги.
     *
     * @param Response|\Throwable|\Closure(Request): Response ...$responses
     */
    public function push(Response|\Throwable|\Closure ...$responses): self
    {
        foreach ($responses as $r) {
            $this->queue[] = $r;
        }

        return $this;
    }

    public function send(Request $request): Response
    {
        $this->requests[] = $request;
        $next = array_shift($this->queue) ?? $this->fallback;
        if ($next === null) {
            throw new \OutOfRangeException(sprintf('MockTransport: no response queued for %s %s', $request->method, $request->url));
        }
        if ($next instanceof \Throwable) {
            throw $next;
        }

        return $next instanceof Response ? $next : $next($request);
    }

    /**
     * Усі отримані запити в порядку надходження.
     *
     * @return list<Request>
     */
    public function requests(): array
    {
        return $this->requests;
    }

    /**
     * Останній отриманий запит.
     */
    public function lastRequest(): Request
    {
        return $this->requests[count($this->requests) - 1] ?? throw new \LogicException('MockTransport: no requests sent');
    }
}
