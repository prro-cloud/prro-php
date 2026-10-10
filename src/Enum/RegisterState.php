<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Enum;

/**
 * Стан каси.
 */
enum RegisterState: string
{
    /** Зміна закрита. */
    case Closed = 'closed';
    /** Зміна відкрита. */
    case Opened = 'opened';
    /** Триває офлайн-сесія. */
    case Offline = 'offline';
    /** Значення, якого ця версія бібліотеки не знає. */
    case Unknown = 'unknown';
}
