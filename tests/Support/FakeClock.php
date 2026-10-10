<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Tests\Support;

use PrroCloud\Prro\Internal\Clock;

/**
 * Годинник без справжнього сну: sleep() лише просуває час і запам'ятовує
 * паузу.
 */
final class FakeClock implements Clock
{
    /** @var list<float> */
    public array $sleeps = [];

    public function __construct(private float $now = 1000.0) {}

    public function now(): float
    {
        return $this->now;
    }

    public function sleep(float $seconds): void
    {
        $this->sleeps[] = $seconds;
        $this->now += $seconds;
    }
}
