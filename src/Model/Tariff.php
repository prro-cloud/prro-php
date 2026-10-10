<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Model;

use PrroCloud\Prro\Internal\Attribute\Json;
use PrroCloud\Prro\Money\Kopecks;

/**
 * Тариф, за яким рахували.
 */
final readonly class Tariff
{
    public function __construct(
        #[Json('plan_id')] public string $planId = '',
        #[Json('price_per_receipt')] public Kopecks $pricePerReceipt = new Kopecks('0'),
    ) {}
}
