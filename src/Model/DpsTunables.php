<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Model;

use PrroCloud\Prro\Internal\Attribute\Json;

/**
 * Пороги детектора доступності ДПС.
 */
final readonly class DpsTunables
{
    /**
     * @param int $windowSeconds        ширина вікна детектора, с
     * @param int $degradedLatencyMs    затримка, від якої відповідь вважають повільною, мс
     * @param int $probeIntervalSeconds крок перевірок, с
     */
    public function __construct(
        #[Json('window_seconds')] public int $windowSeconds = 0,
        #[Json('degraded_latency_ms')] public int $degradedLatencyMs = 0,
        #[Json('probe_interval_seconds')] public int $probeIntervalSeconds = 0,
    ) {}
}
