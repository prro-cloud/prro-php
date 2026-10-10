<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Internal;

use PrroCloud\Prro\Exception\DecodeException;
use PrroCloud\Prro\Exception\InvalidArgumentException;

/**
 * Кодування й розбір JSON з однаковими прапорцями для всієї бібліотеки.
 *
 * @internal
 */
final class Json
{
    private const ENCODE_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        | JSON_PRESERVE_ZERO_FRACTION;

    public static function encode(mixed $value): string
    {
        try {
            return json_encode($value, self::ENCODE_FLAGS);
        } catch (\JsonException $e) {
            throw new InvalidArgumentException('prro: encode request: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Розбирає JSON-об'єкт в асоціативний масив.
     *
     * @return array<mixed>
     *
     * @throws DecodeException тіло не є JSON-об'єктом
     */
    public static function decodeObject(string $json, string $context = 'response'): array
    {
        try {
            $value = json_decode($json, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\JsonException $e) {
            throw new DecodeException('prro: decode ' . $context . ': ' . $e->getMessage(), 0, $e);
        }
        // {} розбирається в [], тож порожній масив — теж об'єкт.
        if (!is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new DecodeException('prro: decode ' . $context . ': not a JSON object');
        }

        return $value;
    }
}
