<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Model;

use PrroCloud\Prro\Internal\Attribute\Json;
use PrroCloud\Prro\Money\Amount;

/**
 * Лічильник готівки до заяви, після неї і поправка між ними.
 */
final readonly class CashBalanceDeclaration
{
    public function __construct(
        #[Json('amount')] public Amount $amount = new Amount('0.00'),
        #[Json('previous')] public Amount $previous = new Amount('0.00'),
        #[Json('adjustment')] public Amount $adjustment = new Amount('0.00'),
    ) {}
}
