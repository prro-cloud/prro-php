<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Input;

use PrroCloud\Prro\Internal\Attribute\Json;
use PrroCloud\Prro\Internal\Hydrator;
use PrroCloud\Prro\Money\Amount;

/**
 * Знижка на позицію.
 */
final readonly class Discount implements \JsonSerializable
{
    public Amount $sum;

    /**
     * @param Amount|string $sum     сума знижки; вказується і для відсоткової знижки
     * @param string|null   $percent позначає знижку як відсоткову: "10"
     */
    public function __construct(
        #[Json('sum')] Amount|string $sum,
        #[Json('percent')] public ?string $percent = null,
    ) {
        $this->sum = Amount::of($sum);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return Hydrator::dehydrate($this);
    }
}
