<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Webhook\Data;

use PrroCloud\Prro\Internal\Attribute\Json;
use PrroCloud\Prro\Money\Amount;

/**
 * Рядок зареєстрованого чека.
 */
final readonly class ReceiptLine
{
    /**
     * @param string      $quantity кількість десятковим рядком: "1", "0.750"
     * @param Amount|null $cost     вартість рядка до знижки
     * @param Amount|null $discount сума знижки
     */
    public function __construct(
        #[Json('code')] public string $code = '',
        #[Json('barcode')] public string $barcode = '',
        #[Json('uktzed')] public string $uktzed = '',
        #[Json('dkpp')] public string $dkpp = '',
        #[Json('name')] public string $name = '',
        #[Json('unit_name')] public string $unitName = '',
        #[Json('quantity')] public string $quantity = '',
        #[Json('price')] public Amount $price = new Amount('0.00'),
        #[Json('cost')] public ?Amount $cost = null,
        #[Json('tax_letters')] public string $taxLetters = '',
        #[Json('discount')] public ?Amount $discount = null,
    ) {}
}
