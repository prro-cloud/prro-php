<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Enum;

/**
 * Звідки подано чек.
 */
enum ReceiptSource: string
{
    /** Через машинне API. */
    case Api = 'api';
    /** З панелі кабінету. */
    case Cabinet = 'cabinet';
    /** Значення, якого ця версія бібліотеки не знає. */
    case Unknown = 'unknown';
}
