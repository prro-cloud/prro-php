<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Webhook\Data;

use PrroCloud\Prro\Internal\Attribute\Json;

/**
 * Дані подій shift.opened і shift.closed.
 */
final readonly class ShiftData
{
    /**
     * @param bool $testing зміна тестова: каса в режимі test
     */
    public function __construct(
        #[Json('cash_register_id')] public string $cashRegisterId = '',
        #[Json('shift_id')] public string $shiftId = '',
        #[Json('task_id')] public string $taskId = '',
        #[Json('testing')] public bool $testing = false,
    ) {}
}
