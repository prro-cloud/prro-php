<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Model;

use PrroCloud\Prro\Internal\Attribute\Json;

/**
 * Результат завдання. Заповнені лише поля, що стосуються його типу:
 * receipt — для чека, shiftOpen — для відкриття зміни, zReport і
 * shiftClose — для закриття тощо.
 */
final readonly class TaskResult
{
    /**
     * @param string $cashRegisterId каса, на якій виконано завдання
     * @param string $shiftId        зміна, у межах якої видано документи завдання
     * @param int    $shiftNumber    наскрізний номер зміни в межах каси
     * @param bool   $testing        документи тестові: каса в режимі test
     */
    public function __construct(
        #[Json('prro_id')] public string $cashRegisterId = '',
        #[Json('shift_id')] public string $shiftId = '',
        #[Json('shift_number')] public int $shiftNumber = 0,
        #[Json('testing')] public bool $testing = false,
        #[Json('receipt')] public ?DocResult $receipt = null,
        #[Json('shift_open')] public ?DocResult $shiftOpen = null,
        #[Json('zreport')] public ?DocResult $zReport = null,
        #[Json('shift_close')] public ?DocResult $shiftClose = null,
        #[Json('offline_end')] public ?DocResult $offlineEnd = null,
        #[Json('offline_abandon')] public ?OfflineAbandonResult $offlineAbandon = null,
        #[Json('reconcile')] public ?ReconcileReport $reconcile = null,
    ) {}
}
