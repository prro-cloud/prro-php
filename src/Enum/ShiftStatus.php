<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Enum;

/**
 * Стан зміни; opening і closing — операція ще в роботі.
 */
enum ShiftStatus: string
{
    case Opening = 'opening';
    case Opened = 'opened';
    case Closing = 'closing';
    case Closed = 'closed';
    /** Значення, якого ця версія бібліотеки не знає. */
    case Unknown = 'unknown';
}
