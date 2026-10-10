<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Model;

use PrroCloud\Prro\Internal\Attribute\Json;
use PrroCloud\Prro\Money\Amount;

/**
 * Лічильник готівки каси. Враховує лише документи, що пройшли через сервіс;
 * від'ємне значення означає, що в касі було більше, ніж побачив сервіс.
 */
final readonly class CashBalance
{
    /**
     * @param \DateTimeImmutable|null $asOf коли залишок рухався востаннє; null — рухів ще не було, тож нуль
     *                                      означає «немає даних», а не «каса порожня»
     */
    public function __construct(
        #[Json('amount')] public Amount $amount = new Amount('0.00'),
        #[Json('as_of')] public ?\DateTimeImmutable $asOf = null,
    ) {}
}
