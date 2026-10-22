<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Model;

use PrroCloud\Prro\Internal\Attribute\Json;
use PrroCloud\Prro\Money\Amount;

/**
 * Підсумки відкритої зміни на цей момент — з них буде складено Z-звіт.
 * Сторнований чек віднімається з продажів чи повернень, куди його було
 * зараховано; службові внесення й видачі рахуються окремо. Розподілу за
 * податками тут немає — він є в підсумках Z-звіту ({@see ZReportTotals}).
 */
final readonly class ShiftTotals
{
    /**
     * @param ShiftTotalsSide $sales   реалізація (поле realiz)
     * @param ShiftTotalsSide $returns повернення (поле return)
     */
    public function __construct(
        #[Json('realiz')] public ShiftTotalsSide $sales = new ShiftTotalsSide(),
        #[Json('return')] public ShiftTotalsSide $returns = new ShiftTotalsSide(),
        #[Json('service_in')] public Amount $serviceIn = new Amount('0.00'),
        #[Json('service_out')] public Amount $serviceOut = new Amount('0.00'),
    ) {}
}
