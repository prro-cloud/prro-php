<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Model;

use PrroCloud\Prro\Internal\Attribute\Json;
use PrroCloud\Prro\Internal\Attribute\ListOf;

/**
 * Результат відмови від офлайн-сесії.
 */
final readonly class OfflineAbandonResult
{
    /**
     * @param string                  $sessionId        сесія, від якої відмовлено
     * @param int                     $dpsSessionId     номер сесії, виданий ДПС
     * @param int                     $heldDocuments    скільки документів сесії ДПС отримала; вони лишаються
     *                                                  зареєстрованими
     * @param list<AbandonedDocument> $abandoned        документи сесії, яких ДПС не отримала; чи видавати
     *                                                  їх знову, вирішує інтеграція
     * @param string                  $closureSessionId сесія, якою відмовлену сесію завершують у ДПС
     * @param bool                    $closed           сесію завершено в ДПС, каса знову онлайн; false —
     *                                                  завершення ще передається
     */
    public function __construct(
        #[Json('session_id')] public string $sessionId = '',
        #[Json('dps_session_id')] public int $dpsSessionId = 0,
        #[Json('held_documents')] public int $heldDocuments = 0,
        #[Json('abandoned'), ListOf(AbandonedDocument::class)] public array $abandoned = [],
        #[Json('closure_session_id')] public string $closureSessionId = '',
        #[Json('closed')] public bool $closed = false,
    ) {}
}
