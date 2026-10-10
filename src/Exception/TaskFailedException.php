<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Exception;

use PrroCloud\Prro\Model\Fault;
use PrroCloud\Prro\Model\Task;

/**
 * 422 (код task_failed): фіскальне завдання завершилося помилкою (немає
 * відкритої зміни, відмова ДПС тощо); опис збою — у {@see ApiException::$fault}.
 *
 * Синхронна відповідь API і {@see \PrroCloud\Prro\Service\TasksService::wait()}
 * повідомляють про збій однаково — цим винятком; після wait() заповнене
 * ще й {@see $task}.
 */
final class TaskFailedException extends ApiException
{
    /**
     * @param array<mixed>|null $details
     * @param Task|null         $task    завдання в стані failed; лише з TasksService::wait()
     */
    public function __construct(
        int $statusCode,
        string $errorCode = '',
        string $errorMessage = '',
        ?array $details = null,
        string $taskId = '',
        ?Fault $fault = null,
        ?\Throwable $previous = null,
        public readonly ?Task $task = null,
    ) {
        parent::__construct($statusCode, $errorCode, $errorMessage, $details, $taskId, $fault, $previous);
    }

    /**
     * Будує виняток із завдання, що завершилося помилкою, — так, ніби
     * збій прийшов синхронною відповіддю.
     *
     * @internal
     */
    public static function fromTask(Task $task): self
    {
        return new self(422, 'task_failed', $task->error, null, $task->id, $task->fault, null, $task);
    }
}
