<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Model;

use PrroCloud\Prro\Internal\Attribute\Json;

/**
 * Підсумок звіряння реєстратора з ДПС: який стан побачили на боці ДПС і що
 * виправили в сервісі.
 */
final readonly class ReconcileReport
{
    /**
     * @param bool                 $shiftAdopted     сервіс прийняв як свою зміну, відкриту на боці ДПС
     * @param bool                 $shiftAbandoned   сервіс закрив у себе зміну, якої на боці ДПС немає
     * @param ReconcileCash|null   $cash             рух готівки зміни за обліком сервісу і за ДПС; довідково
     * @param ReconcileTotals|null $totals           підсумки зміни за обліком сервісу і за ДПС
     * @param bool                 $attentionCleared знято позначку auto_close_failing
     * @param string               $skipped          чому звіряння нічого не змінювало ("offline_session")
     */
    public function __construct(
        #[Json('fiscal_number')] public string $fiscalNumber = '',
        #[Json('dps_shift_opened')] public bool $dpsShiftOpened = false,
        #[Json('dps_next_local_number')] public int $dpsNextLocalNumber = 0,
        #[Json('dps_testing')] public bool $dpsTesting = false,
        #[Json('counter_old')] public int $counterOld = 0,
        #[Json('counter_new')] public int $counterNew = 0,
        #[Json('shift_adopted')] public bool $shiftAdopted = false,
        #[Json('shift_abandoned')] public bool $shiftAbandoned = false,
        #[Json('offline_credentials_refreshed')] public bool $offlineCredentialsRefreshed = false,
        #[Json('cash')] public ?ReconcileCash $cash = null,
        #[Json('totals')] public ?ReconcileTotals $totals = null,
        #[Json('attention_cleared')] public bool $attentionCleared = false,
        #[Json('skipped')] public string $skipped = '',
    ) {}
}
