<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Internal;

use PrroCloud\Prro\Exception\InvalidArgumentException;

/**
 * @internal
 */
final class Lists
{
    /**
     * Перевіряє, що всі елементи масиву — екземпляри class, і повертає їх
     * переліком.
     *
     * @template T of object
     *
     * @param array<mixed>    $items
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    public static function of(array $items, string $class, string $field): array
    {
        $out = [];
        foreach ($items as $i => $item) {
            if (!$item instanceof $class) {
                throw new InvalidArgumentException(sprintf(
                    'prro: %s[%s] must be %s, got %s',
                    $field,
                    $i,
                    $class,
                    get_debug_type($item),
                ));
            }
            $out[] = $item;
        }

        return $out;
    }

    /**
     * Перевіряє, що всі елементи масиву — рядки.
     *
     * @param array<mixed> $items
     *
     * @return list<string>
     */
    public static function strings(array $items, string $field): array
    {
        $out = [];
        foreach ($items as $i => $item) {
            if (!is_string($item)) {
                throw new InvalidArgumentException(sprintf('prro: %s[%s] must be string, got %s', $field, $i, get_debug_type($item)));
            }
            $out[] = $item;
        }

        return $out;
    }
}
