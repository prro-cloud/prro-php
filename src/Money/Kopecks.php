<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Money;

use PrroCloud\Prro\Exception\InvalidArgumentException;

/**
 * Сума тарифікації в копійках десятковим рядком із точністю до 4 знаків
 * ("150.0000" — півтори гривні). Так рахує білінг: ціна чека, баланс,
 * рахунки. Суми в чеках — {@see Amount}.
 */
final readonly class Kopecks implements \JsonSerializable, \Stringable
{
    /** Сума як десятковий рядок. */
    public string $value;

    /**
     * @throws InvalidArgumentException рядок не є десятковим числом із щонайбільше 4 знаками після коми
     */
    public function __construct(string $value)
    {
        if (preg_match('/^-?\d+(?:\.\d{1,4})?$/', $value) !== 1) {
            throw new InvalidArgumentException(sprintf('prro: invalid kopecks "%s"', $value));
        }
        $this->value = $value;
    }

    public static function of(self|string $value): self
    {
        return $value instanceof self ? $value : new self($value);
    }

    /**
     * @internal
     */
    public static function fromResponse(string $value): self
    {
        $k = (new \ReflectionClass(self::class))->newInstanceWithoutConstructor();
        // Ініціалізація readonly-властивості в області класу — легальна; так оминаємо перевірку формату.
        // @phpstan-ignore property.readOnlyAssignNotInConstructor
        $k->value = $value;

        return $k;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
