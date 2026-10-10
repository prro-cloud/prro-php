<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Enum;

/**
 * Чому каса потребує уваги.
 */
enum AttentionReason: string
{
    /** Розбіжності з ДПС усуваються знову і знову. */
    case RecurringRemediation = 'recurring_remediation';
    /**
     * Надсилання офлайн-пакетів зупинено; відновлюють через
     * {@see \PrroCloud\Prro\Service\OfflineSessionsService::retry()}.
     */
    case OfflineSubmissionPaused = 'offline_submission_paused';
    /** Не вдається автоматичне закриття зміни. */
    case AutoCloseFailing = 'auto_close_failing';
    /** Значення, якого ця версія бібліотеки не знає. */
    case Unknown = 'unknown';
}
