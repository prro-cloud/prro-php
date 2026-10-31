<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Webhook\Data;

use PrroCloud\Prro\Internal\Attribute\DurationSeconds;
use PrroCloud\Prro\Internal\Attribute\Json;

/**
 * Дані подій register.offline, register.online і register.offline_limit.
 */
final readonly class OfflineData
{
    /**
     * @param \DateTimeImmutable|null $endedAt            кінець сесії; лише для register.online
     * @param int                     $documents          скільки документів видано в сесії; лише для
     *                                                    register.online
     * @param int                     $sessionUsedSeconds витрачений офлайн-час сесії, с; лише для
     *                                                    register.offline_limit (межа — 36 годин)
     * @param int                     $monthUsedSeconds   витрачений офлайн-час місяця, с; лише для
     *                                                    register.offline_limit (межа — 168 годин)
     * @param bool                    $limitsLifted       межі знято: подія register.offline_limit лише
     *                                                    повідомляє, документи через межі не відхиляються
     */
    public function __construct(
        #[Json('cash_register_id')] public string $cashRegisterId = '',
        #[Json('session_id')] public string $sessionId = '',
        #[Json('dps_session_id')] public int $dpsSessionId = 0,
        #[Json('started_at')] public \DateTimeImmutable $startedAt = new \DateTimeImmutable('@0'),
        #[Json('ended_at')] public ?\DateTimeImmutable $endedAt = null,
        #[Json('documents')] public int $documents = 0,
        #[Json('session_used'), DurationSeconds] public int $sessionUsedSeconds = 0,
        #[Json('month_used'), DurationSeconds] public int $monthUsedSeconds = 0,
        #[Json('limits_lifted')] public bool $limitsLifted = false,
    ) {}
}
