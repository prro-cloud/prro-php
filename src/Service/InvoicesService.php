<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Service;

use PrroCloud\Prro\Internal\Hydrator;
use PrroCloud\Prro\Internal\Requester;
use PrroCloud\Prro\Model\Invoice;

/**
 * Рахунки клієнта.
 */
final readonly class InvoicesService
{
    /** Найбільша сторінка, яку віддає API. */
    public const MAX_PAGE_SIZE = 200;

    /** @internal */
    public function __construct(private Requester $requester) {}

    /**
     * Повертає сторінку рахунків, від найновішого періоду. Усі рахунки
     * поспіль дає {@see all()}.
     *
     * @param int $limit  скільки рахунків повернути: 1–200; 0 — типово 50
     * @param int $offset скільки рахунків пропустити
     *
     * @return list<Invoice>
     */
    public function list(int $limit = 0, int $offset = 0): array
    {
        $query = [];
        if ($limit > 0) {
            $query['limit'] = (string) $limit;
        }
        if ($offset > 0) {
            $query['offset'] = (string) $offset;
        }
        $data = $this->requester->send('GET', '/v1/invoices', $query);

        return Hydrator::hydrateList(Invoice::class, $data ?? [], 'invoices');
    }

    /**
     * Обходить усі рахунки, від найновішого, підвантажуючи сторінки за
     * потреби. Перерваний обхід наступних сторінок не запитує.
     *
     *     foreach ($client->invoices->all() as $invoice) {
     *         echo $invoice->periodStart, ' ', $invoice->amount, ' ', $invoice->status->value, "\n";
     *     }
     *
     * @return \Generator<int, Invoice, mixed, void>
     */
    public function all(): \Generator
    {
        $offset = 0;
        while (true) {
            $page = $this->list(self::MAX_PAGE_SIZE, $offset);
            yield from $page;
            if (count($page) < self::MAX_PAGE_SIZE) {
                return;
            }
            $offset += count($page);
        }
    }
}
