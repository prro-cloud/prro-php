<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Model;

use PrroCloud\Prro\Internal\Attribute\Json;

/**
 * Законодавчі межі офлайн-роботи: 36 годин на сесію і 168 годин на
 * календарний місяць, у секундах.
 */
final readonly class OfflineLimits
{
    public function __construct(
        #[Json('session_seconds')] public int $sessionSeconds = 0,
        #[Json('month_seconds')] public int $monthSeconds = 0,
    ) {}
}
