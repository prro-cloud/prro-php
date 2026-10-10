<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Model;

use PrroCloud\Prro\Enum\TaskStatus;
use PrroCloud\Prro\Enum\TaskType;
use PrroCloud\Prro\Internal\Attribute\Json;

/**
 * Завдання фіскального конвеєра: у нього перетворюється кожна фіскальна
 * операція (чек, зміна, офлайн-сесія).
 */
final readonly class Task
{
    /**
     * @param string                  $id         ідентифікатор завдання; за ним опитують tasks->get()
     * @param \DateTimeImmutable|null $finishedAt момент завершення; null, доки завдання виконується
     * @param string                  $error      текст помилки завдання; структурований опис — у fault
     */
    public function __construct(
        #[Json('task_id')] public string $id = '',
        #[Json('type')] public TaskType $type = TaskType::Unknown,
        #[Json('status')] public TaskStatus $status = TaskStatus::Unknown,
        #[Json('created_at')] public \DateTimeImmutable $createdAt = new \DateTimeImmutable('@0'),
        #[Json('finished_at')] public ?\DateTimeImmutable $finishedAt = null,
        #[Json('error')] public string $error = '',
        #[Json('fault')] public ?Fault $fault = null,
        #[Json('result')] public ?TaskResult $result = null,
    ) {}

    /**
     * Чи завершилося завдання — успішно або помилкою.
     */
    public function isDone(): bool
    {
        return $this->status === TaskStatus::Succeeded || $this->status === TaskStatus::Failed;
    }
}
