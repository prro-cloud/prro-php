<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Internal;

use PrroCloud\Prro\Exception\InvalidArgumentException;

/**
 * Дати й моменти часу у форматах API.
 *
 * @internal
 */
final class DateTimes
{
    private const RFC3339 = '/^(\d{4}-\d{2}-\d{2})[Tt ](\d{2}:\d{2}:\d{2})(?:\.(\d+))?([Zz]|[+-]\d{2}:\d{2})$/';

    /**
     * Розбирає момент RFC 3339. Сервер на Go віддає дріб секунди до 9 цифр,
     * а DateTimeImmutable зберігає не більше 6, тож зайві цифри
     * відкидаються до розбору.
     */
    public static function parse(string $value): \DateTimeImmutable
    {
        if (preg_match(self::RFC3339, $value, $m) !== 1) {
            throw new \UnexpectedValueException(sprintf('invalid RFC 3339 time "%s"', $value));
        }
        $frac = substr(str_pad($m[3], 6, '0'), 0, 6);
        $zone = strtoupper($m[4]) === 'Z' ? '+00:00' : $m[4];
        $t = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.uP', $m[1] . 'T' . $m[2] . '.' . $frac . $zone);
        $errors = \DateTimeImmutable::getLastErrors();
        if ($t === false || ($errors !== false && $errors['warning_count'] > 0)) {
            throw new \UnexpectedValueException(sprintf('invalid RFC 3339 time "%s"', $value));
        }

        return $t;
    }

    /**
     * Форматує момент як RFC 3339 — так, як це робить Go: "Z" для UTC і
     * дріб секунди лише тоді, коли він ненульовий.
     */
    public static function format(\DateTimeInterface $t): string
    {
        $s = $t->format('Y-m-d\TH:i:s');
        $micro = $t->format('u');
        if ($micro !== '000000') {
            $s .= '.' . rtrim($micro, '0');
        }

        return $s . ($t->getOffset() === 0 ? 'Z' : $t->format('P'));
    }

    /**
     * Нормалізує календарну дату: DateTimeInterface — дата в його часовому
     * поясі, рядок — перевіряється на формат РРРР-ММ-ДД.
     */
    public static function date(\DateTimeInterface|string $date, string $field = 'date'): string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d');
        }
        $t = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1 || $t === false || $t->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException(sprintf('prro: %s must be a YYYY-MM-DD date, got "%s"', $field, $date));
        }

        return $date;
    }
}
