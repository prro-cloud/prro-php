<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Input;

use PrroCloud\Prro\Enum\PaymentType;
use PrroCloud\Prro\Internal\Attribute\Json;
use PrroCloud\Prro\Internal\Hydrator;
use PrroCloud\Prro\Money\Amount;

/**
 * Одна оплата чека.
 */
final readonly class Payment implements \JsonSerializable
{
    public Amount $sum;

    public ?Amount $provided;

    /**
     * @param PaymentType        $type     форма оплати
     * @param Amount|string      $sum      сума, віднесена на цю форму оплати
     * @param Amount|string|null $provided отримано готівкою; решта = provided − sum
     * @param string|null        $means    засіб оплати, який друкується замість назви форми: «ПЕРЕКАЗ З
     *                                     КАРТКИ», «ТАЛОН», «Подарунковий сертифікат». Фіскальний код форми
     *                                     лишається за type. Для готівки не приймається
     * @param CardDetails|null   $card     реквізити еквайрингу для оплати карткою
     */
    public function __construct(
        #[Json('type')] public PaymentType $type,
        #[Json('sum')] Amount|string $sum,
        #[Json('provided')] Amount|string|null $provided = null,
        #[Json('means')] public ?string $means = null,
        #[Json('card')] public ?CardDetails $card = null,
    ) {
        $this->sum = Amount::of($sum);
        $this->provided = $provided === null ? null : Amount::of($provided);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return Hydrator::dehydrate($this);
    }
}
