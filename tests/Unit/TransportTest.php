<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Tests\Unit;

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use PHPUnit\Framework\TestCase;
use PrroCloud\Prro\Client;
use PrroCloud\Prro\Exception\TransportException;
use PrroCloud\Prro\Http\CurlTransport;
use PrroCloud\Prro\Http\Psr18Transport;
use PrroCloud\Prro\Http\Request;
use Psr\Http\Message\RequestInterface;

final class TransportTest extends TestCase
{
    /** @var resource|null */
    private static $server;

    private static string $baseUrl = '';

    public static function setUpBeforeClass(): void
    {
        $port = self::freePort();
        $router = __DIR__ . '/../Support/echo-server.php';
        $server = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . $port, $router],
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
        );
        if ($server === false) {
            self::markTestSkipped('cannot start php -S');
        }
        self::$server = $server;
        self::$baseUrl = 'http://127.0.0.1:' . $port;
        for ($i = 0; $i < 100; $i++) {
            $conn = @fsockopen('127.0.0.1', $port);
            if ($conn !== false) {
                fclose($conn);

                return;
            }
            usleep(50_000);
        }
        self::markTestSkipped('php -S did not start');
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$server !== null) {
            proc_terminate(self::$server);
            proc_close(self::$server);
            self::$server = null;
        }
    }

    public function testCurlTransportRoundTrip(): void
    {
        $t = new CurlTransport();
        $r = $t->send(new Request('POST', self::$baseUrl . '/v1/receipts?mode=async', [
            'Content-Type' => 'application/json',
            'Idempotency-Key' => 'order-1',
        ], '{"cashier":"Олена"}'));

        self::assertSame(200, $r->status);
        self::assertSame('application/json', $r->header('content-type'));
        self::assertSame('a, b', $r->header('X-Multi'), 'repeated headers are joined');
        $echo = self::decode($r->body);
        self::assertSame('POST', $echo['method']);
        self::assertSame('/v1/receipts?mode=async', $echo['uri']);
        self::assertSame('{"cashier":"Олена"}', $echo['body']);
        self::assertIsArray($echo['headers']);
        self::assertSame('order-1', $echo['headers']['idempotency-key']);
        self::assertArrayNotHasKey('expect', $echo['headers']);

        // Той самий хендл обслуговує наступний запит без тіла.
        $r = $t->send(new Request('DELETE', self::$baseUrl . '/v1/x'));
        $echo = self::decode($r->body);
        self::assertSame('DELETE', $echo['method']);
        self::assertSame('', $echo['body']);
    }

    public function testCurlTransportStatusAndHeaders(): void
    {
        $r = (new CurlTransport())->send(new Request('GET', self::$baseUrl . '/status/503'));
        self::assertSame(503, $r->status);
        self::assertSame('7', $r->header('Retry-After'));

        $r = (new CurlTransport())->send(new Request('GET', self::$baseUrl . '/redirect'));
        self::assertSame(302, $r->status, 'redirects are not followed');
    }

    public function testClientOverCurl(): void
    {
        $client = new Client(token: 'tok', baseUrl: self::$baseUrl, userAgent: 'it/1.0');
        // Ехо-сервер повертає не конверт версії, тож поле version порожнє — важливий сам запит.
        self::assertSame('', $client->system->version());
    }

    public function testCurlTransportErrors(): void
    {
        try {
            (new CurlTransport(connectTimeout: 1))->send(new Request('GET', 'http://127.0.0.1:' . self::freePort() . '/'));
            self::fail('no exception');
        } catch (TransportException $e) {
            self::assertStringStartsWith('curl error 7', $e->getMessage());
        }

        $this->expectException(TransportException::class);
        (new CurlTransport(timeout: 0.3))->send(new Request('GET', self::$baseUrl . '/sleep'));
    }

    public function testPsr18Transport(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new PsrResponse(201, ['Content-Type' => 'application/json', 'X-Multi' => ['a', 'b']], '{"ok":true}'),
            new ConnectException('refused', new PsrRequest('GET', '/')),
        ]));
        $stack->push(Middleware::history($history));
        $factory = new HttpFactory();
        $t = new Psr18Transport(new Guzzle(['handler' => $stack]), $factory, $factory);

        $r = $t->send(new Request('POST', 'https://api.test/v1/webhooks', ['Idempotency-Key' => 'k'], '{"a":1}'));
        self::assertSame(201, $r->status);
        self::assertSame('{"ok":true}', $r->body);
        self::assertSame('a, b', $r->header('x-multi'));
        self::assertIsArray($history[0]);
        $sent = $history[0]['request'];
        self::assertInstanceOf(RequestInterface::class, $sent);
        self::assertSame('POST', $sent->getMethod());
        self::assertSame('k', $sent->getHeaderLine('Idempotency-Key'));
        self::assertSame('{"a":1}', (string) $sent->getBody());

        $this->expectException(TransportException::class);
        $t->send(new Request('GET', 'https://api.test/'));
    }

    private static function freePort(): int
    {
        $s = stream_socket_server('tcp://127.0.0.1:0');
        if ($s === false) {
            self::fail('no free port');
        }
        $name = (string) stream_socket_get_name($s, false);
        fclose($s);

        return (int) substr($name, (int) strrpos($name, ':') + 1);
    }

    /**
     * @return array<string, mixed>
     */
    private static function decode(string $json): array
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        /** @var array<string, mixed> */
        return $data;
    }
}
