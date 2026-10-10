<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Webhook\Data;

use PrroCloud\Prro\Enum\PaymentType;
use PrroCloud\Prro\Internal\Attribute\Json;
use PrroCloud\Prro\Money\Amount;

/**
 * Форма оплати зареєстрованого чека.
 */
final readonly class ReceiptPayment
{
    /**
     * @param PaymentType|null $type форма оплати, як у запиті чека
     * @param string           $name назва, надрукована на чеку
     */
    public function __construct(
        #[Json('type')] public ?PaymentType $type = null,
        #[Json('name')] public string $name = '',
        #[Json('sum')] public Amount $sum = new Amount('0.00'),
    ) {}
}
