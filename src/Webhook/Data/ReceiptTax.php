<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Webhook\Data;

use PrroCloud\Prro\Internal\Attribute\Json;
use PrroCloud\Prro\Money\Amount;

/**
 * Податок зареєстрованого чека.
 */
final readonly class ReceiptTax
{
    /**
     * @param string $rate     ставка у відсотках: "20.00"
     * @param Amount $turnover оборот за ставкою
     * @param Amount $sum      сума податку
     * @param bool   $excluded податок нараховано понад ціну
     */
    public function __construct(
        #[Json('letter')] public string $letter = '',
        #[Json('name')] public string $name = '',
        #[Json('rate')] public string $rate = '',
        #[Json('turnover')] public Amount $turnover = new Amount('0.00'),
        #[Json('sum')] public Amount $sum = new Amount('0.00'),
        #[Json('excluded')] public bool $excluded = false,
    ) {}
}
