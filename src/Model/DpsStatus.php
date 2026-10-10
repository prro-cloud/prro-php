<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Model;

use PrroCloud\Prro\Enum\DpsMode;
use PrroCloud\Prro\Internal\Attribute\Json;

/**
 * Режим роботи з ДПС і телеметрія, що його пояснює.
 */
final readonly class DpsStatus
{
    /**
     * @param DpsMode                 $effective   підсумковий режим, якого дотримується сервіс: Online або
     *                                             Offline
     * @param DpsMode                 $verdict     вердикт детектора; відрізняє «повільно» (Degraded) від
     *                                             «недоступно»
     * @param \DateTimeImmutable      $since       відколи діє поточний режим effective
     * @param int                     $latencyMs   затримка успішних викликів у вікні детектора, мс
     * @param int                     $samples     скільки викликів у вікні
     * @param int                     $failures    скільки з них невдалих
     * @param \DateTimeImmutable|null $lastOkAt    остання успішна відповідь ДПС
     * @param \DateTimeImmutable|null $lastProbeAt остання перевірка доступності ДПС
     * @param DpsTunables             $tunables    пороги, за якими судять числа вище
     */
    public function __construct(
        #[Json('effective')] public DpsMode $effective = DpsMode::Unknown,
        #[Json('verdict')] public DpsMode $verdict = DpsMode::Unknown,
        #[Json('since')] public \DateTimeImmutable $since = new \DateTimeImmutable('@0'),
        #[Json('latency_ms')] public int $latencyMs = 0,
        #[Json('samples')] public int $samples = 0,
        #[Json('failures')] public int $failures = 0,
        #[Json('last_ok_at')] public ?\DateTimeImmutable $lastOkAt = null,
        #[Json('last_probe_at')] public ?\DateTimeImmutable $lastProbeAt = null,
        #[Json('tunables')] public DpsTunables $tunables = new DpsTunables(),
    ) {}
}
