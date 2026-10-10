<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Internal;

/**
 * Час і очікування для повторів і опитування завдань; у тестах — фейк без
 * справжнього сну.
 *
 * @internal
 */
interface Clock
{
    /** Монотонний час у секундах; придатний лише для обчислення інтервалів. */
    public function now(): float;

    public function sleep(float $seconds): void;
}
