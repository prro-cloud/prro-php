<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Webhook\Data;

use PrroCloud\Prro\Internal\Attribute\Json;

/**
 * Дані події register.remediated: одне автоматичне виправлення розбіжності
 * з ДПС.
 */
final readonly class RemediationData
{
    /**
     * @param string               $action    що виправлено: local_number_synced, shift_adopted, shift_abandoned,
     *                                        zreport_recovered, zreport_autofiled, register_config_filled
     * @param string               $faultCode збій, який спричинив виправлення
     * @param array<string, mixed> $details
     */
    public function __construct(
        #[Json('cash_register_id')] public string $cashRegisterId = '',
        #[Json('task_id')] public string $taskId = '',
        #[Json('action')] public string $action = '',
        #[Json('fault_code')] public string $faultCode = '',
        #[Json('details')] public array $details = [],
    ) {}
}
