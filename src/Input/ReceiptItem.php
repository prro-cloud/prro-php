<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Input;

use PrroCloud\Prro\Internal\Attribute\Json;
use PrroCloud\Prro\Internal\Hydrator;
use PrroCloud\Prro\Internal\Lists;
use PrroCloud\Prro\Money\Amount;

/**
 * Позиція чека.
 */
final readonly class ReceiptItem implements \JsonSerializable
{
    public string $quantity;

    public Amount $price;

    /** @var list<string>|null */
    public ?array $exciseLabels;

    /**
     * @param string                 $name         назва позиції, як її друкують у чеку
     * @param string|int             $quantity     кількість десятковим рядком, до 3 знаків після коми: "1", "0.750"
     * @param Amount|string          $price        ціна за одиницю
     * @param string|null            $taxLetters   податкові літери каси, напр. "А" або "АГ". null застосовує
     *                                             типову літеру каси, "-" робить позицію неоподаткованою
     * @param string|null            $code         внутрішній код товару
     * @param string|null            $barcode      штрихкод товару
     * @param string|null            $uktzed       код УКТЗЕД; взаємовиключний з dkpp
     * @param string|null            $dkpp         код ДКПП; взаємовиключний з uktzed
     * @param int|null               $unitCode     код одиниці виміру (UNITCD)
     * @param string|null            $unitName     назва одиниці виміру: «шт», «кг»
     * @param Discount|null          $discount     знижка на позицію
     * @param list<string>|null      $exciseLabels коди марок акцизного податку
     * @param string|null            $comment      примітка до позиції
     */
    public function __construct(
        #[Json('name')] public string $name,
        #[Json('quantity')] string|int $quantity,
        #[Json('price')] Amount|string $price,
        #[Json('tax_letters')] public ?string $taxLetters = null,
        #[Json('code')] public ?string $code = null,
        #[Json('barcode')] public ?string $barcode = null,
        #[Json('uktzed')] public ?string $uktzed = null,
        #[Json('dkpp')] public ?string $dkpp = null,
        #[Json('unit_code')] public ?int $unitCode = null,
        #[Json('unit_name')] public ?string $unitName = null,
        #[Json('discount')] public ?Discount $discount = null,
        #[Json('excise_labels')] ?array $exciseLabels = null,
        #[Json('comment')] public ?string $comment = null,
    ) {
        $this->quantity = (string) $quantity;
        $this->price = Amount::of($price);
        $this->exciseLabels = $exciseLabels === null ? null : Lists::strings($exciseLabels, 'ReceiptItem.exciseLabels');
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return Hydrator::dehydrate($this);
    }
}
