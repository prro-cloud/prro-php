<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Webhook\Data;

use PrroCloud\Prro\Enum\TaskStatus;
use PrroCloud\Prro\Enum\TaskType;
use PrroCloud\Prro\Internal\Attribute\Json;
use PrroCloud\Prro\Model\Fault;
use PrroCloud\Prro\Model\TaskResult;

/**
 * Дані подій task.completed і task.failed.
 */
final readonly class TaskData
{
    /**
     * @param string          $cashRegisterId каса завдання; порожній для завдань рівня клієнта
     * @param TaskResult|null $result         той самий результат, що повертає tasks->get(); лише для
     *                                        task.completed
     * @param string          $error          текст збою; лише для task.failed
     * @param Fault|null      $fault          структурований опис збою; лише для task.failed
     */
    public function __construct(
        #[Json('task_id')] public string $taskId = '',
        #[Json('type')] public TaskType $type = TaskType::Unknown,
        #[Json('status')] public TaskStatus $status = TaskStatus::Unknown,
        #[Json('cash_register_id')] public string $cashRegisterId = '',
        #[Json('result')] public ?TaskResult $result = null,
        #[Json('error')] public string $error = '',
        #[Json('fault')] public ?Fault $fault = null,
    ) {}
}
