<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Internal;

/**
 * Пауза перед повтором запиту.
 *
 * @internal
 */
final class Backoff
{
    /**
     * Пауза перед повтором attempt (від 0): Retry-After, якщо сервер його
     * надіслав, обрізаний до max; інакше експонента min·2^attempt (не більше
     * max) з випадковим розкидом у межах [d/2, d].
     */
    public static function delay(int $attempt, ?string $retryAfter, float $min, float $max): float
    {
        if ($retryAfter !== null) {
            $d = self::parseRetryAfter($retryAfter);
            if ($d !== null) {
                return min($d, $max);
            }
        }
        $d = $attempt >= 62 ? $max : min($min * (1 << $attempt), $max);
        $micro = (int) round($d * 1e6);

        return random_int(intdiv($micro, 2), $micro) / 1e6;
    }

    /**
     * Розбирає Retry-After: ціле число секунд або HTTP-дата.
     */
    public static function parseRetryAfter(string $value): ?float
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (ctype_digit($value)) {
            return (float) $value;
        }
        $t = \DateTimeImmutable::createFromFormat('D, d M Y H:i:s \G\M\T', $value, new \DateTimeZone('UTC'));
        if ($t === false) {
            return null;
        }

        return max((float) $t->format('U.u') - microtime(true), 0.0);
    }
}
