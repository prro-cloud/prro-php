<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Enum;

/**
 * Операція завдання.
 */
enum TaskType: string
{
    /** Розрахунковий документ. */
    case Receipt = 'receipt';
    /** Відкриття зміни. */
    case OpenShift = 'open_shift';
    /** Z-звіт і закриття зміни. */
    case CloseShift = 'close_shift';
    /** Завершення офлайн-сесії. */
    case CloseOfflineSession = 'close_offline_session';
    /** Відмова від офлайн-сесії. */
    case AbandonOfflineSession = 'abandon_offline_session';
    /** Звіряння з ДПС. */
    case SyncRegister = 'sync_register';
    /** Значення, якого ця версія бібліотеки не знає. */
    case Unknown = 'unknown';
}
