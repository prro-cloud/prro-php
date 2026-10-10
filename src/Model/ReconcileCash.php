<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Model;

use PrroCloud\Prro\Internal\Attribute\Json;
use PrroCloud\Prro\Money\Amount;

/**
 * Готівка зміни за двома підрахунками.
 */
final readonly class ReconcileCash
{
    public function __construct(
        #[Json('ours')] public Amount $ours = new Amount('0.00'),
        #[Json('dps')] public Amount $dps = new Amount('0.00'),
        #[Json('match')] public bool $match = false,
    ) {}
}
