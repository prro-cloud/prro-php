<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Service;

use PrroCloud\Prro\Internal\Hydrator;
use PrroCloud\Prro\Internal\Requester;
use PrroCloud\Prro\Model\Task;

/**
 * Відкриття і закриття змін.
 */
final readonly class ShiftsService
{
    /** @internal */
    public function __construct(private Requester $requester) {}

    /**
     * Відкриває зміну на касі registerId. Якщо каса не в режимі
     * ShiftMode::Manual, крок можна пропустити — перший чек відкриє зміну
     * сам. Завдання, режими і ключ ідемпотентності — як у
     * {@see ReceiptsService::create()}.
     *
     * @param string|null $cashier ім'я касира, яке друкують у документі відкриття
     */
    public function open(string $registerId, ?string $cashier = null, ?string $idempotencyKey = null, bool $async = false): Task
    {
        $path = Requester::path('/v1/cash-registers/%s/shifts', $registerId);
        $body = $cashier === null ? null : ['cashier' => $cashier];
        $data = $this->requester->send('POST', $path, body: $body, fiscal: true, idempotencyKey: $idempotencyKey, async: $async);

        return Hydrator::hydrate(Task::class, $data ?? []);
    }

    /**
     * Закриває поточну зміну каси registerId: однією операцією формує
     * Z-звіт і документ закриття — обидва в result->zReport і
     * result->shiftClose.
     */
    public function close(string $registerId, ?string $idempotencyKey = null, bool $async = false): Task
    {
        $path = Requester::path('/v1/cash-registers/%s/shifts/current', $registerId);
        $data = $this->requester->send('DELETE', $path, fiscal: true, idempotencyKey: $idempotencyKey, async: $async);

        return Hydrator::hydrate(Task::class, $data ?? []);
    }
}
