<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Money;

use PrroCloud\Prro\Exception\InvalidArgumentException;

/**
 * Сума в гривнях десятковим рядком ("259.90"), у тій самій формі, що в
 * чеках: до 2 знаків після коми. Гроші в API ніколи не передаються числом з
 * рухомою комою.
 *
 * Методи, що приймають суму, приймають і рядок: price: '65.00'. Для сум,
 * обчислених у копійках, є {@see Amount::fromKop()}.
 */
final readonly class Amount implements \JsonSerializable, \Stringable
{
    private const PATTERN = '/^(-?)(\d+)(?:\.(\d{1,2}))?$/';

    /** Сума як десятковий рядок. */
    public string $value;

    /**
     * @throws InvalidArgumentException рядок не є десятковим числом із щонайбільше 2 знаками після коми
     */
    public function __construct(string $value)
    {
        self::parse($value);
        $this->value = $value;
    }

    /**
     * Приводить суму до Amount: рядок перевіряється одразу, тож помилку
     * видно до запиту.
     */
    public static function of(self|string $value): self
    {
        return $value instanceof self ? $value : new self($value);
    }

    /**
     * Перетворює суму в копійках на Amount: 6500 → "65.00".
     */
    public static function fromKop(int $kop): self
    {
        $digits = str_pad(ltrim((string) $kop, '-'), 3, '0', STR_PAD_LEFT);

        // Рядок правильний за побудовою; крайні значення int (понад межу kop())
        // теж представні, як і в prro-go.
        return self::fromResponse(($kop < 0 ? '-' : '') . substr($digits, 0, -2) . '.' . substr($digits, -2));
    }

    /**
     * Суму з відповіді API беремо як є: неочікуваний формат не має ламати
     * розбір відповіді, а помилку покаже {@see kop()}.
     *
     * @internal
     */
    public static function fromResponse(string $value): self
    {
        $a = (new \ReflectionClass(self::class))->newInstanceWithoutConstructor();
        // Ініціалізація readonly-властивості в області класу — легальна; так оминаємо перевірку формату.
        // @phpstan-ignore property.readOnlyAssignNotInConstructor
        $a->value = $value;

        return $a;
    }

    /**
     * Повертає суму в копійках: "65.5" → 6550.
     *
     * @throws InvalidArgumentException сума має недійсний формат або не вміщується в int
     */
    public function kop(): int
    {
        return self::parse($this->value);
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }

    private static function parse(string $value): int
    {
        if (preg_match(self::PATTERN, $value, $m) !== 1) {
            throw new InvalidArgumentException(sprintf('prro: invalid amount "%s"', $value));
        }
        $whole = ltrim($m[2], '0');
        if (strlen($whole) > 17 || ($whole !== '' && (int) $whole > intdiv(PHP_INT_MAX - 99, 100))) {
            throw new InvalidArgumentException(sprintf('prro: invalid amount "%s": out of range', $value));
        }
        $kop = (int) $whole * 100 + (int) str_pad($m[3] ?? '', 2, '0');

        return $m[1] === '-' ? -$kop : $kop;
    }
}
