<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Webhook\Data;

use PrroCloud\Prro\Internal\Attribute\Json;
use PrroCloud\Prro\Internal\Attribute\ListOf;
use PrroCloud\Prro\Model\AbandonedDocument;

/**
 * Дані події register.offline_abandoned: сесія, від якої відмовлено, і
 * документи, яких ДПС так і не отримала. Чи видавати ці продажі знову,
 * вирішує інтеграція.
 */
final readonly class OfflineAbandonedData
{
    /**
     * @param list<AbandonedDocument> $documents
     */
    public function __construct(
        #[Json('cash_register_id')] public string $cashRegisterId = '',
        #[Json('session_id')] public string $sessionId = '',
        #[Json('dps_session_id')] public int $dpsSessionId = 0,
        #[Json('task_id')] public string $taskId = '',
        #[Json('documents'), ListOf(AbandonedDocument::class)] public array $documents = [],
    ) {}
}
