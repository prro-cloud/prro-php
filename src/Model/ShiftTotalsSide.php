<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Model;

use PrroCloud\Prro\Internal\Attribute\Json;
use PrroCloud\Prro\Internal\Attribute\ListOf;
use PrroCloud\Prro\Money\Amount;

/**
 * Один бік підсумків зміни: кількість документів, сума і розклад за формами
 * оплати.
 */
final readonly class ShiftTotalsSide
{
    /**
     * @param list<PayFormTotal> $payForms
     */
    public function __construct(
        #[Json('count')] public int $count = 0,
        #[Json('sum')] public Amount $sum = new Amount('0.00'),
        #[Json('pay_forms'), ListOf(PayFormTotal::class)] public array $payForms = [],
    ) {}
}
