<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Enum;

/**
 * Стан доставки події.
 */
enum DeliveryStatus: string
{
    /** У черзі. */
    case Pending = 'pending';
    /** Триває спроба. */
    case Delivering = 'delivering';
    /** Доставлено. */
    case Delivered = 'delivered';
    /** Спроба невдала, буде повтор. */
    case Failed = 'failed';
    /** Спроби вичерпано. */
    case Dead = 'dead';
    /** Значення, якого ця версія бібліотеки не знає; лише у відповідях. */
    case Unknown = 'unknown';
}
