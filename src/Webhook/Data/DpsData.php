<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Webhook\Data;

use PrroCloud\Prro\Enum\DpsMode;
use PrroCloud\Prro\Internal\Attribute\Json;

/**
 * Дані подій system.dps_*: режим роботи з ДПС після переходу і його
 * причина.
 */
final readonly class DpsData
{
    public function __construct(
        #[Json('verdict')] public DpsMode $verdict = DpsMode::Unknown,
        #[Json('override')] public string $override = '',
        #[Json('effective')] public DpsMode $effective = DpsMode::Unknown,
        #[Json('cause')] public string $cause = '',
        #[Json('occurred_at')] public \DateTimeImmutable $occurredAt = new \DateTimeImmutable('@0'),
    ) {}
}
