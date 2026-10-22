<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Input;

use PrroCloud\Prro\Internal\Attribute\Json;
use PrroCloud\Prro\Internal\Hydrator;

/**
 * Чек, який скасовує сторно. На відміну від {@see ReceiptOriginal}, лише
 * фіскальний номер: сторнують тільки останній документ тієї самої каси.
 */
final readonly class StornoOriginal implements \JsonSerializable
{
    /**
     * @param string $fiscalNumber фіскальний номер чека, який скасовують
     */
    public function __construct(
        #[Json('fiscal_number')] public string $fiscalNumber,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return Hydrator::dehydrate($this);
    }
}
