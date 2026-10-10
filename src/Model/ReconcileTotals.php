<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Model;

use PrroCloud\Prro\Internal\Attribute\Json;

/**
 * Підсумки зміни за двома обліками.
 */
final readonly class ReconcileTotals
{
    /**
     * @param ShiftTotalsBrief|null $dps           відсутнє, якщо ДПС не повернула підсумки
     * @param string                $zReportSource з яких підсумків буде подано Z-звіт: local або dps
     * @param bool                  $forced        це звіряння перевело Z-звіт на підсумки ДПС
     * @param string                $held          чому розбіжність не виправлено: dps_totals_unavailable,
     *                                             shift_closing або offline_backlog
     */
    public function __construct(
        #[Json('ours')] public ShiftTotalsBrief $ours = new ShiftTotalsBrief(),
        #[Json('dps')] public ?ShiftTotalsBrief $dps = null,
        #[Json('match')] public bool $match = false,
        #[Json('z_report_source')] public string $zReportSource = '',
        #[Json('forced')] public bool $forced = false,
        #[Json('held')] public string $held = '',
    ) {}
}
