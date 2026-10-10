<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Tests\Support;

use PrroCloud\Prro\Client;
use PrroCloud\Prro\Http\Request;
use PrroCloud\Prro\Http\Response;
use PrroCloud\Prro\Testing\MockTransport;

/**
 * Спільні помічники тестів: клієнт до MockTransport і фікстури.
 */
trait ClientFactory
{
    private FakeClock $clock;

    private function client(MockTransport $transport, mixed ...$options): Client
    {
        $this->clock = new FakeClock();

        /** @phpstan-ignore argument.type */
        return new Client(...[
            'token' => 'test-token',
            'baseUrl' => 'https://api.test/',
            'transport' => $transport,
            'clock' => $this->clock,
            ...$options,
        ]);
    }

    /**
     * Клієнт до транспорту, що на кожен запит відповідає status і body.
     *
     * @param array<mixed>|string $body
     *
     * @return array{Client, MockTransport}
     */
    private function stub(int $status, array|string $body = []): array
    {
        $mock = MockTransport::repeat($status === 204 ? new Response(204) : MockTransport::json($status, $body));

        return [$this->client($mock), $mock];
    }

    private static function fixture(string $name): string
    {
        $raw = file_get_contents(__DIR__ . '/../fixtures/' . $name . '.json');
        if ($raw === false) {
            throw new \RuntimeException('no fixture ' . $name);
        }

        return $raw;
    }

    /**
     * Розбирає тіло запиту як JSON-об'єкт.
     *
     * @return array<mixed>
     */
    private static function body(Request $r): array
    {
        $decoded = json_decode($r->body ?? '', true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \RuntimeException('body is not an object: ' . $r->body);
        }

        return $decoded;
    }

    private static function assertRequest(Request $r, string $method, string $path, string $query = ''): void
    {
        self::assertSame($method . ' ' . $path . '?' . $query, $r->method . ' ' . $r->path() . '?' . $r->query());
    }
}
