<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Enum;

/**
 * Стан завдання.
 */
enum TaskStatus: string
{
    /** У черзі. */
    case Pending = 'pending';
    /** Виконується. */
    case Processing = 'processing';
    /** Завершено успішно, результат у result. */
    case Succeeded = 'succeeded';
    /** Завершено помилкою, опис у fault. */
    case Failed = 'failed';
    /** Значення, якого ця версія бібліотеки не знає. */
    case Unknown = 'unknown';
}
