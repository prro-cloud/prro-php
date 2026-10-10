<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Service;

use PrroCloud\Prro\Internal\Hydrator;
use PrroCloud\Prro\Internal\Requester;
use PrroCloud\Prro\Model\Task;

/**
 * Керування офлайн-сесією каси.
 */
final readonly class OfflineSessionsService
{
    /** @internal */
    public function __construct(private Requester $requester) {}

    /**
     * Передає пакети офлайн-сесії каси registerId до ДПС і завершує сесію,
     * не чекаючи фонової передачі. Якщо ДПС недоступна, завдання
     * завершується збоєм state.offline_close_needs_dps — сесію буде передано
     * й завершено автоматично, щойно зв'язок відновиться.
     *
     * @param string|null $cashier ім'я касира, яке друкують у документі завершення сесії
     */
    public function close(string $registerId, ?string $cashier = null, ?string $idempotencyKey = null, bool $async = false): Task
    {
        $path = Requester::path('/v1/cash-registers/%s/offline-session/close', $registerId);
        $body = $cashier === null ? null : ['cashier' => $cashier];
        $data = $this->requester->send('POST', $path, body: $body, fiscal: true, idempotencyKey: $idempotencyKey, async: $async);

        return Hydrator::hydrate(Task::class, $data ?? []);
    }

    /**
     * Відновлює надсилання офлайн-пакетів, зупинене після того, як ДПС
     * відхилила пакет сесії. Спершу усуньте причину. Повертає, скільки
     * сесій повернуто до надсилання; 0 — зупинених не було.
     */
    public function retry(string $registerId): int
    {
        $data = $this->requester->send('POST', Requester::path('/v1/cash-registers/%s/offline-session/retry', $registerId));

        return Hydrator::int($data ?? [], 'resumed_sessions');
    }

    /**
     * Відмовляється від офлайн-сесії, яку ДПС не приймає. Доступно лише для
     * сесії із зупиненим надсиланням і лише коли ДПС відповідає. Документи,
     * яких ДПС не отримала, стають abandoned: їхніх фіскальних номерів у ДПС
     * не існує. Перелік — у result->offlineAbandon; чи видавати ці продажі
     * знову, вирішує інтеграція.
     */
    public function abandon(string $registerId, ?string $idempotencyKey = null, bool $async = false): Task
    {
        $path = Requester::path('/v1/cash-registers/%s/offline-session/abandon', $registerId);
        $data = $this->requester->send('POST', $path, fiscal: true, idempotencyKey: $idempotencyKey, async: $async);

        return Hydrator::hydrate(Task::class, $data ?? []);
    }
}
