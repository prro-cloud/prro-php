<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Internal;

/**
 * Тривалість у форматі Go ("35h12m0s", "1.5h", "300ms"), якою сервіс
 * передає витрачений офлайн-час.
 *
 * @internal
 */
final class GoDuration
{
    /** @var array<string, int> одиниця → наносекунд */
    private const UNITS = [
        'ns' => 1,
        'us' => 1_000,
        'µs' => 1_000,
        'μs' => 1_000,
        'ms' => 1_000_000,
        's' => 1_000_000_000,
        'm' => 60_000_000_000,
        'h' => 3_600_000_000_000,
    ];

    /**
     * Повертає тривалість у цілих секундах, відкидаючи дріб.
     */
    public static function toSeconds(string $value): int
    {
        return intdiv(self::toNanoseconds($value), 1_000_000_000);
    }

    public static function toNanoseconds(string $value): int
    {
        $s = $value;
        $neg = false;
        if ($s !== '' && ($s[0] === '-' || $s[0] === '+')) {
            $neg = $s[0] === '-';
            $s = substr($s, 1);
        }
        if ($s === '0') {
            return 0;
        }
        if ($s === '') {
            throw self::invalid($value);
        }

        $total = 0;
        while ($s !== '') {
            if (preg_match('/^(\d*)(?:\.(\d*))?(ns|us|µs|μs|ms|s|m|h)/u', $s, $m) !== 1 || ($m[1] === '' && $m[2] === '')) {
                throw self::invalid($value);
            }
            $unit = self::UNITS[$m[3]];
            $whole = $m[1] === '' ? 0 : (int) $m[1];
            $frac = $m[2];
            $ns = $whole * $unit;
            if ($frac !== '') {
                $ns += (int) round((float) ('0.' . $frac) * $unit);
            }
            $total += $ns;
            $s = substr($s, strlen($m[0]));
        }

        return $neg ? -$total : $total;
    }

    private static function invalid(string $value): \UnexpectedValueException
    {
        return new \UnexpectedValueException(sprintf('invalid duration "%s"', $value));
    }
}
