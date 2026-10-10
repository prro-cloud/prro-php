<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Enum;

/**
 * Стан рахунка.
 */
enum InvoiceStatus: string
{
    /** Чернетка. */
    case Draft = 'draft';
    /** Виставлено. */
    case Issued = 'issued';
    /** Оплачено. */
    case Paid = 'paid';
    /** Прострочено. */
    case Overdue = 'overdue';
    /** Значення, якого ця версія бібліотеки не знає. */
    case Unknown = 'unknown';
}
