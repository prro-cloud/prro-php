<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Enum;

/**
 * Як керують змінами каси.
 */
enum ShiftMode: string
{
    /** Клієнт відкриває і закриває зміни сам. */
    case Manual = 'manual';
    /** Зміна закривається в кожен із shiftCloseTimes, наступний чек відкриває нову. */
    case RoundTheClock = 'round_the_clock';
    /** Денне вікно між двома межами shiftCloseTimes. */
    case WorkingHours = 'working_hours';
    /** Значення, якого ця версія бібліотеки не знає. */
    case Unknown = 'unknown';
}
