<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Model;

use PrroCloud\Prro\Internal\Attribute\Json;
use PrroCloud\Prro\Money\Amount;

/**
 * Накопичення за однією формою оплати, як у Z-звіті.
 */
final readonly class PayFormTotal
{
    /**
     * @param int    $code PAYFORMCD
     * @param string $name PAYFORMNM
     */
    public function __construct(
        #[Json('code')] public int $code = 0,
        #[Json('name')] public string $name = '',
        #[Json('sum')] public Amount $sum = new Amount('0.00'),
    ) {}
}
