<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Model;

use PrroCloud\Prro\Enum\InvoiceStatus;
use PrroCloud\Prro\Internal\Attribute\Json;
use PrroCloud\Prro\Money\Kopecks;

/**
 * Рахунок за один розрахунковий період.
 */
final readonly class Invoice
{
    /**
     * @param string $periodStart початок періоду, включно (РРРР-ММ-ДД)
     * @param string $periodEnd   кінець періоду, включно (РРРР-ММ-ДД)
     * @param string $currency    валюта за ISO 4217: "UAH"
     */
    public function __construct(
        #[Json('id')] public string $id = '',
        #[Json('period_start')] public string $periodStart = '',
        #[Json('period_end')] public string $periodEnd = '',
        #[Json('amount')] public Kopecks $amount = new Kopecks('0'),
        #[Json('currency')] public string $currency = '',
        #[Json('status')] public InvoiceStatus $status = InvoiceStatus::Unknown,
        #[Json('created_at')] public \DateTimeImmutable $createdAt = new \DateTimeImmutable('@0'),
    ) {}
}
