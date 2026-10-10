<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Service;

use PrroCloud\Prro\Internal\DateTimes;
use PrroCloud\Prro\Internal\Hydrator;
use PrroCloud\Prro\Internal\Requester;
use PrroCloud\Prro\Model\BillingUsage;

/**
 * Передплачений баланс і тарифікація.
 */
final readonly class BillingService
{
    /** @internal */
    public function __construct(private Requester $requester) {}

    /**
     * Повертає передплачений баланс і підсумок тарифікації за період.
     * Порожні межі — поточний календарний місяць за UTC. Тарифікація
     * асинхронна, тож підсумок може відставати від останнього чека на
     * кілька секунд.
     *
     * @param \DateTimeInterface|string|null $from початок періоду, включно: РРРР-ММ-ДД або дата
     * @param \DateTimeInterface|string|null $to   кінець періоду, не включно. Період понад 366 днів — 422
     */
    public function usage(\DateTimeInterface|string|null $from = null, \DateTimeInterface|string|null $to = null): BillingUsage
    {
        $query = [];
        if ($from !== null && $from !== '') {
            $query['from'] = DateTimes::date($from, 'from');
        }
        if ($to !== null && $to !== '') {
            $query['to'] = DateTimes::date($to, 'to');
        }
        $data = $this->requester->send('GET', '/v1/billing/usage', $query);

        return Hydrator::hydrate(BillingUsage::class, $data ?? []);
    }
}
