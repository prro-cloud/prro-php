<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Service;

use PrroCloud\Prro\Enum\TaskStatus;
use PrroCloud\Prro\Exception\TaskFailedException;
use PrroCloud\Prro\Exception\WaitTimeoutException;
use PrroCloud\Prro\Internal\Clock;
use PrroCloud\Prro\Internal\Hydrator;
use PrroCloud\Prro\Internal\Requester;
use PrroCloud\Prro\Model\Task;

/**
 * Стан і результат фіскальних завдань.
 */
final readonly class TasksService
{
    /** @internal */
    public function __construct(
        private Requester $requester,
        private Clock $clock,
        private float $pollMin,
        private float $pollMax,
    ) {}

    /**
     * Повертає завдання в поточному стані. Завдання, що завершилося
     * помилкою, — це успішна відповідь зі status === TaskStatus::Failed, а
     * не виняток.
     */
    public function get(string $id): Task
    {
        $data = $this->requester->send('GET', Requester::path('/v1/tasks/%s', $id));

        return Hydrator::hydrate(Task::class, $data ?? []);
    }

    /**
     * Опитує завдання, доки воно не завершиться, з паузою, що зростає від
     * 0,5 до 5 секунд.
     *
     * Завдання, що завершилося помилкою, — {@see TaskFailedException}, як у
     * синхронній відповіді: опис збою — у $e->fault, саме завдання — у
     * $e->task.
     *
     * @param float $timeout скільки секунд чекати щонайбільше
     *
     * @throws TaskFailedException  завдання завершилося помилкою
     * @throws WaitTimeoutException завдання не завершилося за timeout; останній стан — у $e->lastTask
     */
    public function wait(string $id, float $timeout = 120.0): Task
    {
        $deadline = $this->clock->now() + $timeout;
        $interval = $this->pollMin;
        while (true) {
            $task = $this->get($id);
            if ($task->status === TaskStatus::Succeeded) {
                return $task;
            }
            if ($task->status === TaskStatus::Failed) {
                throw TaskFailedException::fromTask($task);
            }
            $remaining = $deadline - $this->clock->now();
            if ($remaining <= 0) {
                throw new WaitTimeoutException(
                    sprintf('prro: task %s is still %s after %.1f s', $task->id, $task->status->value, $timeout),
                    $task,
                );
            }
            $this->clock->sleep(min($interval, $remaining));
            $interval = min($interval * 1.5, $this->pollMax);
        }
    }
}
