<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PrroCloud\Prro\Client;
use PrroCloud\Prro\Enum\DpsMode;
use PrroCloud\Prro\Enum\FaultClass;
use PrroCloud\Prro\Exception\ApiException;
use PrroCloud\Prro\Exception\DecodeException;
use PrroCloud\Prro\Exception\ForbiddenException;
use PrroCloud\Prro\Exception\IdempotencyKeyReusedException;
use PrroCloud\Prro\Exception\NotFoundException;
use PrroCloud\Prro\Exception\PaymentRequiredException;
use PrroCloud\Prro\Exception\PrroException;
use PrroCloud\Prro\Exception\TaskFailedException;
use PrroCloud\Prro\Exception\TransportException;
use PrroCloud\Prro\Exception\UnauthorizedException;
use PrroCloud\Prro\Exception\ValidationException;
use PrroCloud\Prro\Exception\WebhookLimitReachedException;
use PrroCloud\Prro\Http\Request;
use PrroCloud\Prro\Http\Response;
use PrroCloud\Prro\Internal\Backoff;
use PrroCloud\Prro\Internal\Uuid;
use PrroCloud\Prro\Testing\MockTransport;
use PrroCloud\Prro\Tests\Support\ClientFactory;
use Psr\Log\AbstractLogger;

final class ClientTest extends TestCase
{
    use ClientFactory;

    private const UUID_V7 = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    private const TASK = ['task_id' => 't1', 'type' => 'open_shift', 'status' => 'succeeded', 'created_at' => '2026-08-03T01:07:53Z'];

    public function testRequestHeaders(): void
    {
        $mock = new MockTransport(MockTransport::json(200, [
            'client_id' => 'c1', 'scopes' => ['fiscal'], 'all_cash_registers' => true, 'jti' => 'j1',
        ]));
        $client = $this->client($mock, userAgent: 'my-shop/1.0', language: 'en');

        $me = $client->system->whoAmI();

        $r = $mock->lastRequest();
        self::assertRequest($r, 'GET', '/v1/whoami');
        self::assertSame('https://api.test/v1/whoami', $r->url);
        self::assertSame('application/json', $r->header('Accept'));
        self::assertSame('Bearer test-token', $r->header('Authorization'));
        self::assertStringStartsWith('my-shop/1.0 prro-php/' . Client::VERSION . ' (PHP ', (string) $r->header('User-Agent'));
        self::assertSame('en', $r->header('Accept-Language'));
        self::assertNull($r->header('Idempotency-Key'), 'GET carries Idempotency-Key');
        self::assertNull($r->header('Content-Type'), 'Content-Type without body');
        self::assertNull($r->body);

        self::assertSame('c1', $me->clientId);
        self::assertSame('j1', $me->tokenId);
        self::assertSame(['fiscal'], $me->scopes);
        self::assertTrue($me->allCashRegisters);
        self::assertNull($me->expiresAt);
    }

    public function testDefaultsWithoutOptionalHeaders(): void
    {
        $mock = new MockTransport(MockTransport::json(200, ['version' => 'v0.3.0']));
        $client = new Client(token: '', transport: $mock);

        self::assertSame('v0.3.0', $client->system->version());
        $r = $mock->lastRequest();
        self::assertSame(Client::DEFAULT_BASE_URL . '/v1/version', $r->url);
        self::assertNull($r->header('Authorization'));
        self::assertNull($r->header('Accept-Language'));
        self::assertStringStartsWith('prro-php/' . Client::VERSION . ' (PHP ' . PHP_VERSION . '; ', (string) $r->header('User-Agent'));
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function expiresAtCases(): iterable
    {
        yield 'RFC 3339' => ['"2026-10-07T18:20:50Z"', '2026-10-07T18:20:50+00:00'];
        yield 'Unix seconds' => ['1791397250', '2026-10-07T18:20:50+00:00'];
        yield 'null' => ['null', null];
    }

    #[DataProvider('expiresAtCases')]
    public function testIdentityExpiresAt(string $raw, ?string $want): void
    {
        [$client] = $this->stub(200, '{"client_id":"c1","jti":"j1","expires_at":' . $raw . '}');
        $me = $client->system->whoAmI();
        self::assertSame($want, $me->expiresAt?->format('c'));
        self::assertSame('c1', $me->clientId);
    }

    public function testIdentityExpiresAtWrongType(): void
    {
        [$client] = $this->stub(200, '{"expires_at":true}');
        $this->expectException(DecodeException::class);
        $client->system->whoAmI();
    }

    public function testFiscalRequestGeneratesStableKey(): void
    {
        $mock = new MockTransport(
            MockTransport::json(503, ['code' => 'unavailable', 'message' => 'busy']),
            MockTransport::json(202, self::TASK),
        );
        $client = $this->client($mock);

        $task = $client->shifts->open('r1', cashier: 'Каса 1', async: true);

        self::assertSame('t1', $task->id);
        $requests = $mock->requests();
        self::assertCount(2, $requests);
        foreach ($requests as $r) {
            self::assertRequest($r, 'POST', '/v1/cash-registers/r1/shifts', 'mode=async');
            self::assertSame('application/json', $r->header('Content-Type'));
            self::assertSame('{"cashier":"Каса 1"}', $r->body);
        }
        $key = (string) $requests[0]->header('Idempotency-Key');
        self::assertMatchesRegularExpression(self::UUID_V7, $key);
        self::assertSame($key, $requests[1]->header('Idempotency-Key'), 'one key reused on retry');
    }

    public function testFiscalRequestExplicitKey(): void
    {
        $mock = new MockTransport(MockTransport::json(200, self::TASK));
        $client = $this->client($mock);

        $client->shifts->close('r1', idempotencyKey: 'order-10412');

        $r = $mock->lastRequest();
        self::assertSame('order-10412', $r->header('Idempotency-Key'));
        self::assertSame('', $r->query(), 'sync mode by default');
        self::assertNull($r->body);
    }

    public function testNoRetryForUnsafeRequest(): void
    {
        [$client, $mock] = $this->stub(503, ['code' => 'unavailable', 'message' => 'busy']);
        try {
            $client->webhooks->create('https://example.com/hook');
            self::fail('no exception');
        } catch (ApiException $e) {
            self::assertSame(503, $e->statusCode);
        }
        self::assertCount(1, $mock->requests());
    }

    public function testRetryExhausted(): void
    {
        $mock = MockTransport::repeat(new Response(502, [], '<html>bad gateway</html>'));
        $client = $this->client($mock, maxRetries: 3);
        try {
            $client->system->version();
            self::fail('no exception');
        } catch (ApiException $e) {
            self::assertSame(ApiException::class, $e::class, 'no subclass for a non-API body');
            self::assertSame(502, $e->statusCode);
            self::assertSame('', $e->errorCode);
            self::assertSame('<html>bad gateway</html>', $e->errorMessage);
            self::assertSame('prro: 502: <html>bad gateway</html>', $e->getMessage());
        }
        self::assertCount(4, $mock->requests(), '1 + 3 retries');
        self::assertCount(3, $this->clock->sleeps);
    }

    public function testRetryOnNetworkError(): void
    {
        $mock = new MockTransport(MockTransport::networkError(), MockTransport::json(200, ['version' => 'v0.3.0']));
        $client = $this->client($mock);
        self::assertSame('v0.3.0', $client->system->version());
        self::assertCount(2, $mock->requests());
    }

    public function testNetworkErrorAfterRetries(): void
    {
        $mock = MockTransport::repeat(static fn(Request $r): Response => throw new TransportException('connection refused'));
        $client = $this->client($mock, maxRetries: 0);
        try {
            $client->system->version();
            self::fail('no exception');
        } catch (TransportException $e) {
            self::assertSame('prro: GET /v1/version: connection refused', $e->getMessage());
            self::assertInstanceOf(TransportException::class, $e->getPrevious());
        }
        self::assertCount(1, $mock->requests(), 'maxRetries: 0 disables retries');
    }

    public function testRetryAfterIsCapped(): void
    {
        $mock = new MockTransport(
            MockTransport::json(429, ['code' => 'rate_limited', 'message' => 'slow down'], ['Retry-After' => '120']),
            MockTransport::json(503, ['code' => 'unavailable', 'message' => 'busy'], ['Retry-After' => '3']),
            MockTransport::json(200, ['version' => 'v1']),
        );
        $client = $this->client($mock);
        self::assertSame('v1', $client->system->version());
        self::assertSame([8.0, 3.0], $this->clock->sleeps);
    }

    public function testBackoff(): void
    {
        for ($attempt = 0; $attempt < 70; $attempt++) {
            $d = Backoff::delay($attempt, null, 0.5, 8.0);
            $ceil = $attempt >= 62 ? 8.0 : min(0.5 * (2 ** $attempt), 8.0);
            self::assertGreaterThanOrEqual($ceil / 2, $d, "attempt {$attempt}");
            self::assertLessThanOrEqual($ceil, $d, "attempt {$attempt}");
        }
        self::assertSame(3.0, Backoff::delay(0, '3', 0.5, 8.0));
        self::assertSame(8.0, Backoff::delay(0, '120', 0.5, 8.0), 'Retry-After not capped');

        $date = gmdate('D, d M Y H:i:s \G\M\T', time() + 3);
        $d = Backoff::delay(0, $date, 0.5, 8.0);
        self::assertGreaterThan(1.0, $d);
        self::assertLessThanOrEqual(3.0, $d);
        self::assertSame(0.0, Backoff::parseRetryAfter('Wed, 21 Oct 2015 07:28:00 GMT'), 'past date');
        self::assertNull(Backoff::parseRetryAfter('soon'));
        self::assertNull(Backoff::parseRetryAfter('-1'));
    }

    /**
     * @return iterable<string, array{int, string, class-string<ApiException>}>
     */
    public static function errorCases(): iterable
    {
        yield 'unauthorized' => [401, '{"code":"unauthorized","message":"token revoked"}', UnauthorizedException::class];
        yield 'forbidden' => [403, '{"code":"forbidden","message":"scope"}', ForbiddenException::class];
        yield 'not found' => [404, '{"code":"not_found","message":"cash register not found"}', NotFoundException::class];
        yield 'payment' => [402, '{"code":"payment_required","message":"balance"}', PaymentRequiredException::class];
        yield 'key reused' => [409, '{"code":"idempotency_key_reused","message":"other op"}', IdempotencyKeyReusedException::class];
        yield 'webhook limit' => [409, '{"code":"webhook_limit_reached","message":"limit"}', WebhookLimitReachedException::class];
        yield 'validation' => [422, '{"code":"validation_failed","message":"items","details":[{"field":"items"}]}', ValidationException::class];
        yield 'status fallback' => [404, 'not found', NotFoundException::class];
        yield 'code wins over status' => [400, '{"code":"not_found","message":"x"}', NotFoundException::class];
        yield 'unknown code' => [409, '{"code":"delivery_in_flight","message":"x"}', ApiException::class];
    }

    /**
     * @param class-string<ApiException> $want
     */
    #[DataProvider('errorCases')]
    public function testErrors(int $status, string $body, string $want): void
    {
        [$client] = $this->stub($status, $body);
        try {
            $client->system->whoAmI();
            self::fail('no exception');
        } catch (ApiException $e) {
            self::assertSame($want, $e::class);
            self::assertInstanceOf(PrroException::class, $e);
            self::assertSame($status, $e->statusCode);
        }
    }

    public function testValidationDetails(): void
    {
        [$client] = $this->stub(422, '{"code":"validation_failed","message":"items","details":[{"field":"items"}]}');
        try {
            $client->system->whoAmI();
            self::fail('no exception');
        } catch (ValidationException $e) {
            self::assertSame([['field' => 'items']], $e->details);
            self::assertSame('items', $e->errorMessage);
        }
    }

    public function testTaskFailedError(): void
    {
        [$client] = $this->stub(422, '{"code":"task_failed","message":"no open shift","details":{
            "task_id":"t1","fault":{"code":"state.shift_not_open","class":"precursor","message":"Зміну не відкрито"}}}');
        try {
            $client->shifts->close('r1');
            self::fail('no exception');
        } catch (TaskFailedException $e) {
            self::assertSame('t1', $e->taskId);
            self::assertNotNull($e->fault);
            self::assertSame(FaultClass::Precursor, $e->fault->class);
            self::assertSame('Зміну не відкрито', $e->fault->message);
            self::assertNull($e->task, 'task is filled only by wait()');
            self::assertSame('prro: 422 task_failed (state.shift_not_open): no open shift', $e->getMessage());
        }
    }

    public function testNonApiBodyIsTruncated(): void
    {
        $body = 'a' . str_repeat('ж', 300); // 601 байт: межа 512 припадає посеред символу
        [$client] = $this->stub(500, $body);
        try {
            $client->webhooks->create('https://example.com');
            self::fail('no exception');
        } catch (ApiException $e) {
            self::assertSame('a' . str_repeat('ж', 255) . '…', $e->errorMessage);
            self::assertTrue(mb_check_encoding($e->errorMessage, 'UTF-8'));
        }

        [$client] = $this->stub(503, '');
        try {
            $client->webhooks->create('https://example.com');
            self::fail('no exception');
        } catch (ApiException $e) {
            self::assertSame('service unavailable', $e->errorMessage);
        }
    }

    public function testDecodeError(): void
    {
        [$client] = $this->stub(200, '{"version":');
        try {
            $client->system->version();
            self::fail('no exception');
        } catch (DecodeException $e) {
            self::assertInstanceOf(\JsonException::class, $e->getPrevious());
        }
    }

    public function testDps(): void
    {
        [$client, $mock] = $this->stub(200, self::fixture('dps_status'));
        $s = $client->system->dps();
        self::assertRequest($mock->lastRequest(), 'GET', '/v1/system/dps');
        self::assertSame(DpsMode::Online, $s->effective);
        self::assertSame(DpsMode::Degraded, $s->verdict);
        self::assertSame(1500, $s->tunables->degradedLatencyMs);
        self::assertSame('2026-07-14T10:15:04+00:00', $s->since->format('c'));
        self::assertNull($s->lastOkAt);
    }

    public function testOptions(): void
    {
        $mock = MockTransport::repeat(MockTransport::json(503, []));
        $client = new Client(token: 'tok', baseUrl: '', maxRetries: -1, userAgent: '  ', transport: $mock);
        try {
            $client->system->version();
        } catch (ApiException) {
        }
        self::assertCount(1, $mock->requests(), 'negative maxRetries means 0');
        $r = $mock->lastRequest();
        self::assertStringStartsWith(Client::DEFAULT_BASE_URL . '/', $r->url);
        self::assertStringStartsWith('prro-php/', (string) $r->header('User-Agent'));
    }

    public function testLoggerOmitsSecrets(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<array{string, array<mixed>}> */
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = [(string) $message, $context];
            }
        };
        $mock = new MockTransport(MockTransport::networkError(), MockTransport::json(201, self::TASK));
        $client = $this->client($mock, logger: $logger);

        $client->shifts->open('r1', cashier: 'Секретний касир');

        self::assertCount(2, $logger->records);
        [$message, $context] = $logger->records[1];
        self::assertSame('prro: request', $message);
        self::assertSame('POST', $context['method']);
        self::assertSame('/v1/cash-registers/r1/shifts', $context['path']);
        self::assertSame(2, $context['attempt']);
        self::assertSame(201, $context['status']);
        self::assertArrayHasKey('duration_ms', $context);
        self::assertSame('prro: request failed', $logger->records[0][0]);
        $dump = json_encode($logger->records, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        self::assertStringNotContainsString('test-token', $dump);
        self::assertStringNotContainsString('Секретний', $dump);
    }

    public function testUuidV7(): void
    {
        $seen = [];
        $prev = '';
        for ($i = 0; $i < 1000; $i++) {
            $k = Uuid::v7();
            self::assertMatchesRegularExpression(self::UUID_V7, $k);
            self::assertArrayNotHasKey($k, $seen);
            $seen[$k] = true;
            // Перші 48 бітів — мілісекунди: ключі не спадають.
            self::assertGreaterThanOrEqual(substr($prev, 0, 13), substr($k, 0, 13));
            $prev = $k;
        }
        $ms = hexdec(str_replace('-', '', substr(Uuid::v7(), 0, 13)));
        self::assertEqualsWithDelta(microtime(true) * 1000, $ms, 1000);
    }
}
