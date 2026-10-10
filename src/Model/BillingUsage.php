<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Model;

use PrroCloud\Prro\Internal\Attribute\Json;
use PrroCloud\Prro\Money\Kopecks;

/**
 * Баланс і підсумок тарифікації за період. Суми — у копійках
 * ({@see Kopecks}).
 */
final readonly class BillingUsage
{
    /**
     * @param Kopecks $softLimit нижня межа балансу: поки balance < softLimit, нові чеки відхиляються з
     *                           PaymentRequiredException
     * @param bool    $blocked   чеки зараз заблоковано: балансом або рішенням оператора
     * @param int     $receipts  кількість тарифікованих чеків за період
     */
    public function __construct(
        #[Json('balance')] public Kopecks $balance = new Kopecks('0'),
        #[Json('soft_limit')] public Kopecks $softLimit = new Kopecks('0'),
        #[Json('blocked')] public bool $blocked = false,
        #[Json('period')] public BillingPeriod $period = new BillingPeriod(),
        #[Json('receipts')] public int $receipts = 0,
        #[Json('charged')] public Kopecks $charged = new Kopecks('0'),
        #[Json('tariff')] public Tariff $tariff = new Tariff(),
    ) {}
}
