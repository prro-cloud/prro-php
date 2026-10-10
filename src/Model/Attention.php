<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Model;

use PrroCloud\Prro\Enum\AttentionReason;
use PrroCloud\Prro\Internal\Attribute\Json;

/**
 * Позначка «каса потребує уваги».
 */
final readonly class Attention
{
    public function __construct(
        #[Json('reason')] public AttentionReason $reason = AttentionReason::Unknown,
        #[Json('since')] public ?\DateTimeImmutable $since = null,
    ) {}
}
