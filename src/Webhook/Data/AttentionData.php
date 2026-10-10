<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Webhook\Data;

use PrroCloud\Prro\Enum\AttentionReason;
use PrroCloud\Prro\Internal\Attribute\Json;

/**
 * Дані події register.needs_attention: автоматичні виправлення не
 * тримаються, на касу має поглянути людина.
 */
final readonly class AttentionData
{
    /**
     * @param string $remedy    яке виправлення повторюється; для AttentionReason::RecurringRemediation
     * @param int    $count     скільки разів; для AttentionReason::RecurringRemediation
     * @param string $sessionId зупинена сесія; для AttentionReason::OfflineSubmissionPaused
     * @param string $error     причина зупинки; для AttentionReason::OfflineSubmissionPaused
     */
    public function __construct(
        #[Json('cash_register_id')] public string $cashRegisterId = '',
        #[Json('reason')] public AttentionReason $reason = AttentionReason::Unknown,
        #[Json('remedy')] public string $remedy = '',
        #[Json('count')] public int $count = 0,
        #[Json('session_id')] public string $sessionId = '',
        #[Json('error')] public string $error = '',
    ) {}
}
