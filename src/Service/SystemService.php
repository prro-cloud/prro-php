<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Service;

use PrroCloud\Prro\Internal\Hydrator;
use PrroCloud\Prro\Internal\Requester;
use PrroCloud\Prro\Model\DpsStatus;
use PrroCloud\Prro\Model\Identity;

/**
 * Службові маршрути: версія сервісу, дозволи токена, режим роботи з ДПС.
 */
final readonly class SystemService
{
    /** @internal */
    public function __construct(private Requester $requester) {}

    /**
     * Повертає версію збірки сервісу: "dev" у локальній збірці, інакше тег
     * релізу ("v0.3.0"). Маршрут не потребує токена.
     */
    public function version(): string
    {
        return Hydrator::string($this->requester->send('GET', '/v1/version') ?? [], 'version');
    }

    /**
     * Повертає клієнта і дозволи токена — найпростіший спосіб перевірити,
     * що токен дійсний.
     */
    public function whoAmI(): Identity
    {
        return Hydrator::hydrate(Identity::class, $this->requester->send('GET', '/v1/whoami') ?? []);
    }

    /**
     * Повертає поточний режим роботи сервісу з ДПС; доступний будь-якому
     * машинному токену.
     */
    public function dps(): DpsStatus
    {
        return Hydrator::hydrate(DpsStatus::class, $this->requester->send('GET', '/v1/system/dps') ?? []);
    }
}
