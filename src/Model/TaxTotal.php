<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Model;

use PrroCloud\Prro\Internal\Attribute\Json;
use PrroCloud\Prro\Money\Amount;

/**
 * Податок за однією ставкою: оборот і сума податку — у підсумках Z-звіту
 * ({@see ZReportTotalsSide::$taxes}) і в даних події receipt.registered.
 */
final readonly class TaxTotal
{
    /**
     * @param string $letter   податкова літера: "А"
     * @param string $name     назва податку: "ПДВ"
     * @param string $rate     ставка у відсотках: "20.00"
     * @param Amount $turnover оборот — сума продажів за цією ставкою
     * @param Amount $sum      сума податку
     * @param bool   $excluded податок додано до ціни, а не включено в неї
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
