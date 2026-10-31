<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Model;

use PrroCloud\Prro\Internal\Attribute\Json;

/**
 * Законодавчі межі офлайн-роботи: 36 годин на сесію і 168 годин на
 * календарний місяць, у секундах. Використаний час рахують від початку
 * сесії або від $restoredAt, якщо він пізніший.
 */
final readonly class OfflineLimits
{
    /**
     * @param bool                    $enforced   межі діють. false — межі знято за пунктом 13 розділу
     *                                            «Прикінцеві положення» Закону № 265: час офлайн-роботи
     *                                            рахується, але документи через межі не відхиляються.
     *                                            Сервер до 0.10.0 поля не надсилає, і воно читається
     *                                            як false
     * @param \DateTimeImmutable|null $restoredAt коли межі повернули після зняття; null — межі не знімали
     */
    public function __construct(
        #[Json('session_seconds')] public int $sessionSeconds = 0,
        #[Json('month_seconds')] public int $monthSeconds = 0,
        #[Json('enforced')] public bool $enforced = false,
        #[Json('restored_at')] public ?\DateTimeImmutable $restoredAt = null,
    ) {}
}
