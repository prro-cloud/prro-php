<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Model;

use PrroCloud\Prro\Internal\Attribute\Json;

/**
 * Період підсумку тарифікації: from включно, to не включно. Дати — рядки
 * РРРР-ММ-ДД.
 */
final readonly class BillingPeriod
{
    public function __construct(
        #[Json('from')] public string $from = '',
        #[Json('to')] public string $to = '',
    ) {}
}
