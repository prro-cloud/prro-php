<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Enum;

/**
 * Режим каси. Документи зміни мають режим, у якому її відкрито; змінити
 * режим можна лише при закритій зміні.
 */
enum RegisterMode: string
{
    /** Бойовий режим. */
    case Production = 'production';
    /** Документи позначені як тестові. */
    case Test = 'test';
    /** Значення, якого ця версія бібліотеки не знає. */
    case Unknown = 'unknown';
}
