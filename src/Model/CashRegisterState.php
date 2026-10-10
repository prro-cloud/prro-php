<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Model;

use PrroCloud\Prro\Enum\RegisterMode;
use PrroCloud\Prro\Enum\RegisterState;
use PrroCloud\Prro\Enum\ShiftMode;
use PrroCloud\Prro\Internal\Attribute\Json;

/**
 * Поточний стан каси: режим, зміна, готівка, офлайн-сесія.
 */
final readonly class CashRegisterState
{
    /**
     * @param bool                $offlineReady            каса має видані ДПС офлайн-реквізити
     * @param ShiftMode|null      $shiftMode               як керують змінами каси
     * @param list<string>        $shiftCloseTimes         час щоденного закриття зміни за київським часом
     *                                                     ("21:00"); порожній для ShiftMode::Manual
     * @param CashBalance|null    $cashBalance             лічильник готівки каси
     * @param Shift|null          $currentShift            поточна зміна; null після закриття
     * @param ShiftTotals|null    $shiftTotals             підсумки відкритої зміни, з яких буде побудовано
     *                                                     Z-звіт
     * @param OfflineSession|null $offlineSession          присутня, поки каса працює офлайн
     * @param OfflineLimits       $offlineLimits           законодавчі межі офлайн-роботи
     * @param int                 $offlineMonthUsedSeconds час офлайн-роботи в цьому календарному місяці (за
     *                                                     київським часом), с
     * @param Attention|null      $attention               присутня, коли каса потребує уваги; знімається
     *                                                     через cashRegisters->clearAttention()
     */
    public function __construct(
        #[Json('id')] public string $id = '',
        #[Json('fiscal_number')] public string $fiscalNumber = '',
        #[Json('local_number')] public string $localNumber = '',
        #[Json('mode')] public RegisterMode $mode = RegisterMode::Unknown,
        #[Json('state')] public RegisterState $state = RegisterState::Unknown,
        #[Json('offline_ready')] public bool $offlineReady = false,
        #[Json('shift_mode')] public ?ShiftMode $shiftMode = null,
        #[Json('shift_close_times')] public array $shiftCloseTimes = [],
        #[Json('cash_balance')] public ?CashBalance $cashBalance = null,
        #[Json('current_shift')] public ?Shift $currentShift = null,
        #[Json('shift_totals')] public ?ShiftTotals $shiftTotals = null,
        #[Json('offline_session')] public ?OfflineSession $offlineSession = null,
        #[Json('offline_limits')] public OfflineLimits $offlineLimits = new OfflineLimits(),
        #[Json('offline_month_used_seconds')] public int $offlineMonthUsedSeconds = 0,
        #[Json('attention')] public ?Attention $attention = null,
    ) {}
}
