<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Internal\Attribute;

/**
 * Поле — тривалість у форматі Go ("35h12m0s"), параметр отримує цілі
 * секунди.
 *
 * @internal
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final readonly class DurationSeconds {}
