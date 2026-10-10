<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Enum;

/**
 * Вид розрахункового документа.
 */
enum ReceiptType: string
{
    /** Реалізація. */
    case Sale = 'sale';
    /** Повернення. */
    case Return = 'return';
    /** Сторно. */
    case Storno = 'storno';
    /** Службове внесення. */
    case ServiceIn = 'service_in';
    /** Службова видача (інкасація). */
    case ServiceOut = 'service_out';
    /** Значення, якого ця версія бібліотеки не знає; лише у відповідях. */
    case Unknown = 'unknown';
}
