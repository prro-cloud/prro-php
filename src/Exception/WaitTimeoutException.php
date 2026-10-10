<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Exception;

use PrroCloud\Prro\Model\Task;

/**
 * {@see \PrroCloud\Prro\Service\TasksService::wait()} не дочекався завершення
 * завдання. Завдання продовжує виконуватися: перевірте його пізніше або
 * дочекайтеся вебхука.
 */
final class WaitTimeoutException extends \RuntimeException implements PrroException
{
    /**
     * @param Task|null $lastTask останній отриманий стан завдання
     */
    public function __construct(string $message, public readonly ?Task $lastTask = null)
    {
        parent::__construct($message);
    }
}
