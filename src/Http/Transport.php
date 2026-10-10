<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Http;

use PrroCloud\Prro\Exception\TransportException;

/**
 * Транспорт виконує один HTTP-запит. Повтори, заголовки й розбір відповіді
 * бере на себе клієнт, тож транспорт лише передає байти.
 *
 * Типовий транспорт — {@see CurlTransport}; для Guzzle, Symfony HttpClient
 * та інших PSR-18 клієнтів — {@see Psr18Transport}; у тестах —
 * {@see \PrroCloud\Prro\Testing\MockTransport}.
 */
interface Transport
{
    /**
     * Надсилає запит і повертає відповідь з будь-яким HTTP-статусом.
     *
     * @throws TransportException мережа, DNS, TLS або тайм-аут: відповіді немає
     */
    public function send(Request $request): Response;
}
