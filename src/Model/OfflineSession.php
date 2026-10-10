<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Model;

use PrroCloud\Prro\Internal\Attribute\Json;

/**
 * Офлайн-сесія каси.
 */
final readonly class OfflineSession
{
    /**
     * @param int                     $dpsSessionId      номер сесії, виданий ДПС; входить у фіскальний номер
     *                                                   офлайн-документа
     * @param \DateTimeImmutable      $startedAt         початок сесії; від нього рахують 36-годинну межу
     * @param \DateTimeImmutable|null $lastSignificantAt останній документ, що продовжує сесію
     */
    public function __construct(
        #[Json('id')] public string $id = '',
        #[Json('dps_session_id')] public int $dpsSessionId = 0,
        #[Json('started_at')] public \DateTimeImmutable $startedAt = new \DateTimeImmutable('@0'),
        #[Json('documents')] public int $documents = 0,
        #[Json('last_significant_at')] public ?\DateTimeImmutable $lastSignificantAt = null,
    ) {}
}
