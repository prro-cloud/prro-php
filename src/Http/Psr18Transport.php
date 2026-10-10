<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Http;

use PrroCloud\Prro\Exception\TransportException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Адаптер до будь-якого PSR-18 клієнта: Guzzle, Symfony HttpClient тощо.
 * Потрібні пакети psr/http-client і psr/http-factory.
 *
 *     $http = new \GuzzleHttp\Client(['timeout' => 60]);
 *     $factory = new \GuzzleHttp\Psr7\HttpFactory();
 *     $client = new Client(token: $token, transport: new Psr18Transport($http, $factory, $factory));
 *
 * Тайм-аути задаються в самому PSR-18 клієнті: параметри timeout і
 * connectTimeout клієнта PRRO.cloud на нього не діють.
 */
final readonly class Psr18Transport implements Transport
{
    public function __construct(
        private ClientInterface $client,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
    ) {}

    public function send(Request $request): Response
    {
        $psr = $this->requestFactory->createRequest($request->method, $request->url);
        foreach ($request->headers as $name => $value) {
            $psr = $psr->withHeader($name, $value);
        }
        if ($request->body !== null) {
            $psr = $psr->withBody($this->streamFactory->createStream($request->body));
        }

        try {
            $response = $this->client->sendRequest($psr);
        } catch (ClientExceptionInterface $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }

        /** @var array<string, list<string>> $headers */
        $headers = $response->getHeaders();

        return new Response($response->getStatusCode(), $headers, (string) $response->getBody());
    }
}
