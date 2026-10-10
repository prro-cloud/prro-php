<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Internal;

/**
 * UUIDv7 (RFC 9562): 48 бітів часу Unix у мілісекундах, версія, варіант і
 * випадкові біти. Ключі впорядковані за часом і не повторюються.
 *
 * @internal
 */
final class Uuid
{
    public static function v7(): string
    {
        $ms = (int) (microtime(true) * 1000);
        $bytes = substr(pack('J', $ms), 2, 6) . random_bytes(10);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x70);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20),
        );
    }
}
