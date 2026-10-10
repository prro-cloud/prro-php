<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Model;

use PrroCloud\Prro\Internal\Attribute\Json;
use PrroCloud\Prro\Money\Amount;

/**
 * Суми й кількості чеків зміни, за якими ДПС звіряє Z-звіт.
 */
final readonly class ShiftTotalsBrief
{
    public function __construct(
        #[Json('sales')] public Amount $sales = new Amount('0.00'),
        #[Json('sales_count')] public int $salesCount = 0,
        #[Json('returns')] public Amount $returns = new Amount('0.00'),
        #[Json('returns_count')] public int $returnsCount = 0,
        #[Json('service_input')] public Amount $serviceInput = new Amount('0.00'),
        #[Json('service_output')] public Amount $serviceOutput = new Amount('0.00'),
    ) {}
}
