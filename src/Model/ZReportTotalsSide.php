<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Model;

use PrroCloud\Prro\Internal\Attribute\Json;
use PrroCloud\Prro\Internal\Attribute\ListOf;
use PrroCloud\Prro\Money\Amount;

/**
 * Продажі або повернення за зміну в Z-звіті: кількість чеків, сума, розподіл
 * за формами оплати й за податками.
 */
final readonly class ZReportTotalsSide
{
    /**
     * @param int                $count    кількість чеків
     * @param list<PayFormTotal> $payForms суми за формами оплати; порожній, якщо чеків немає
     * @param list<TaxTotal>     $taxes    податки за ставками; порожній, якщо податків не було
     */
    public function __construct(
        #[Json('count')] public int $count = 0,
        #[Json('sum')] public Amount $sum = new Amount('0.00'),
        #[Json('pay_forms'), ListOf(PayFormTotal::class)] public array $payForms = [],
        #[Json('taxes'), ListOf(TaxTotal::class)] public array $taxes = [],
    ) {}
}
