<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Internal;

use PrroCloud\Prro\Exception\DecodeException;
use PrroCloud\Prro\Internal\Attribute\DurationSeconds;
use PrroCloud\Prro\Internal\Attribute\Json as JsonName;
use PrroCloud\Prro\Internal\Attribute\ListOf;
use PrroCloud\Prro\Money\Amount;
use PrroCloud\Prro\Money\Kopecks;

/**
 * Будує моделі відповідей з розібраного JSON за атрибутами параметрів
 * конструктора: #[Json('name')] — ім'я поля, #[ListOf(X::class)] — тип
 * елементів масиву, #[DurationSeconds] — тривалість Go у секундах.
 *
 * Невідомі поля ігноруються, відсутні й null — беруть значення параметра
 * за замовчуванням, невідомі значення перелічень стають case Unknown: так
 * відповідь новішої версії сервера не ламає розбір.
 *
 * @internal
 */
final class Hydrator
{
    /**
     * @var array<class-string, array{\ReflectionClass<object>, list<array{param: string, json: string, type: string, nullable: bool, default: bool, list: class-string|null, duration: bool}>}>
     */
    private static array $meta = [];

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     * @param array<mixed>    $data
     *
     * @return T
     *
     * @throws DecodeException поле має не той тип
     */
    public static function hydrate(string $class, array $data): object
    {
        try {
            return self::build($class, $data, self::shortName($class));
        } catch (\UnexpectedValueException $e) {
            throw new DecodeException('prro: decode response: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Будує перелік моделей із поля key відповіді-обгортки
     * ({"cash_registers": [...]}).
     *
     * @template T of object
     *
     * @param class-string<T> $class
     * @param array<mixed>    $data
     *
     * @return list<T>
     */
    public static function hydrateList(string $class, array $data, string $key): array
    {
        $items = $data[$key] ?? [];
        if (!is_array($items)) {
            throw new DecodeException(sprintf('prro: decode response: %s: want array, got %s', $key, get_debug_type($items)));
        }
        $out = [];
        foreach (array_values($items) as $i => $item) {
            if (!is_array($item)) {
                throw new DecodeException(sprintf('prro: decode response: %s[%d]: want object, got %s', $key, $i, get_debug_type($item)));
            }
            $out[] = self::hydrate($class, $item);
        }

        return $out;
    }

    /**
     * @param array<mixed> $data
     */
    public static function string(array $data, string $key): string
    {
        return self::scalar($data, $key, 'string', '');
    }

    /**
     * @param array<mixed> $data
     */
    public static function int(array $data, string $key): int
    {
        return self::scalar($data, $key, 'int', 0);
    }

    /**
     * @param array<mixed> $data
     */
    public static function bool(array $data, string $key): bool
    {
        return self::scalar($data, $key, 'bool', false);
    }

    /**
     * Перетворює вхідний DTO на поля JSON за тими самими атрибутами #[Json]:
     * null пропускається, момент часу стає рядком RFC 3339. Порожній масив
     * лишається — він відрізняється від null.
     *
     * @return array<string, mixed>
     */
    public static function dehydrate(object $input): array
    {
        $out = [];
        foreach (self::meta($input::class)[1] as $f) {
            $value = get_object_vars($input)[$f['param']] ?? null;
            if ($value === null) {
                continue;
            }
            $out[$f['json']] = $value instanceof \DateTimeInterface ? DateTimes::format($value) : $value;
        }

        return $out;
    }

    /**
     * Імена полів JSON моделі — для тестів відповідності контракту.
     *
     * @param class-string $class
     *
     * @return list<string>
     */
    public static function fields(string $class): array
    {
        return array_map(static fn(array $f): string => $f['json'], self::meta($class)[1]);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     * @param array<mixed>    $data
     *
     * @return T
     */
    private static function build(string $class, array $data, string $path): object
    {
        [$reflection, $fields] = self::meta($class);
        $args = [];
        foreach ($fields as $f) {
            $value = $data[$f['json']] ?? null;
            if ($value === null) {
                if ($f['default']) {
                    continue;
                }
                if (!$f['nullable']) {
                    throw new \UnexpectedValueException(sprintf('%s.%s: missing', $path, $f['json']));
                }
                $args[$f['param']] = null;
                continue;
            }
            $field = $path . '.' . $f['json'];
            if ($f['duration']) {
                if (!is_string($value)) {
                    throw self::mismatch($field, 'duration string', $value);
                }
                $args[$f['param']] = GoDuration::toSeconds($value);
                continue;
            }
            if ($f['list'] !== null) {
                if (!is_array($value)) {
                    throw self::mismatch($field, 'array', $value);
                }
                $items = [];
                foreach (array_values($value) as $i => $item) {
                    $items[] = self::convert($f['list'], $item, $field . '[' . $i . ']');
                }
                $args[$f['param']] = $items;
                continue;
            }
            $args[$f['param']] = self::convert($f['type'], $value, $field);
        }

        /** @var T */
        return $reflection->newInstanceArgs($args);
    }

    private static function convert(string $type, mixed $value, string $field): mixed
    {
        switch ($type) {
            case 'mixed':
                return $value;
            case 'string':
                return is_string($value) ? $value : throw self::mismatch($field, 'string', $value);
            case 'bool':
                return is_bool($value) ? $value : throw self::mismatch($field, 'bool', $value);
            case 'int':
                if (is_int($value)) {
                    return $value;
                }
                if (is_float($value) && floor($value) === $value && abs($value) < 2 ** 53) {
                    return (int) $value;
                }

                throw self::mismatch($field, 'int', $value);
            case 'float':
                return is_int($value) || is_float($value) ? (float) $value : throw self::mismatch($field, 'number', $value);
            case 'array':
                return is_array($value) ? $value : throw self::mismatch($field, 'array', $value);
            case \DateTimeImmutable::class:
            case \DateTimeInterface::class:
                // Число — секунди Unix: так деякі сервери віддають expires_at у /v1/whoami.
                if (is_int($value)) {
                    return (new \DateTimeImmutable('@' . $value))->setTimezone(new \DateTimeZone('UTC'));
                }
                if (!is_string($value)) {
                    throw self::mismatch($field, 'RFC 3339 time', $value);
                }
                try {
                    return DateTimes::parse($value);
                } catch (\UnexpectedValueException $e) {
                    throw new \UnexpectedValueException($field . ': ' . $e->getMessage(), 0, $e);
                }
            case Amount::class:
                return is_string($value) ? Amount::fromResponse($value) : throw self::mismatch($field, 'decimal string', $value);
            case Kopecks::class:
                return is_string($value) ? Kopecks::fromResponse($value) : throw self::mismatch($field, 'decimal string', $value);
        }

        if (is_subclass_of($type, \BackedEnum::class)) {
            if (!is_string($value) && !is_int($value)) {
                throw self::mismatch($field, 'enum value', $value);
            }
            $case = $type::tryFrom($value) ?? $type::tryFrom('unknown');
            if ($case === null) {
                throw new \UnexpectedValueException(sprintf('%s: unknown value "%s"', $field, $value));
            }

            return $case;
        }
        if (class_exists($type)) {
            if (!is_array($value)) {
                throw self::mismatch($field, 'object', $value);
            }

            return self::build($type, $value, $field);
        }

        throw new \LogicException(sprintf('prro: %s: unsupported type %s', $field, $type));
    }

    /**
     * @param class-string $class
     *
     * @return array{\ReflectionClass<object>, list<array{param: string, json: string, type: string, nullable: bool, default: bool, list: class-string|null, duration: bool}>}
     */
    private static function meta(string $class): array
    {
        if (isset(self::$meta[$class])) {
            return self::$meta[$class];
        }
        $reflection = new \ReflectionClass($class);
        $fields = [];
        foreach ($reflection->getConstructor()?->getParameters() ?? [] as $p) {
            $json = $p->getAttributes(JsonName::class)[0] ?? null;
            if ($json === null) {
                continue;
            }
            // Складений тип (Amount|string у вхідних DTO) годиться лише для
            // dehydrate(); hydrate() відхилить його в convert().
            $type = $p->getType();
            $list = $p->getAttributes(ListOf::class)[0] ?? null;
            $fields[] = [
                'param' => $p->getName(),
                'json' => $json->newInstance()->name,
                'type' => $type instanceof \ReflectionNamedType ? $type->getName() : '',
                'nullable' => $type === null || $type->allowsNull(),
                'default' => $p->isDefaultValueAvailable(),
                'list' => $list?->newInstance()->class,
                'duration' => $p->getAttributes(DurationSeconds::class) !== [],
            ];
        }

        return self::$meta[$class] = [$reflection, $fields];
    }

    /**
     * @template T of string|int|bool
     *
     * @param array<mixed> $data
     * @param T            $default
     *
     * @return T
     */
    private static function scalar(array $data, string $key, string $type, string|int|bool $default): string|int|bool
    {
        $value = $data[$key] ?? null;
        if ($value === null) {
            return $default;
        }
        try {
            /** @var T */
            return self::convert($type, $value, $key);
        } catch (\UnexpectedValueException $e) {
            throw new DecodeException('prro: decode response: ' . $e->getMessage(), 0, $e);
        }
    }

    private static function mismatch(string $field, string $want, mixed $got): \UnexpectedValueException
    {
        return new \UnexpectedValueException(sprintf('%s: want %s, got %s', $field, $want, get_debug_type($got)));
    }

    private static function shortName(string $class): string
    {
        $pos = strrpos($class, '\\');

        return $pos === false ? $class : substr($class, $pos + 1);
    }
}
