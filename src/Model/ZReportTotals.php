<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Model;

use PrroCloud\Prro\Internal\Attribute\Json;
use PrroCloud\Prro\Money\Amount;

/**
 * Підсумки Z-звіту — ті самі суми, що в документі, надісланому до ДПС.
 * Якщо за зміну не було повернень, службових внесень чи видач, відповідні
 * суми нульові. Z-звіт офлайн-сесії (DocResult::$offline) надходить до ДПС
 * пізніше, разом з іншими документами сесії.
 */
final readonly class ZReportTotals
{
    /**
     * @param ZReportTotalsSide $sales      продажі (поле realiz)
     * @param ZReportTotalsSide $returns    повернення (поле return)
     * @param Amount            $serviceIn  службові внесення
     * @param Amount            $serviceOut службові видачі
     */
    public function __construct(
        #[Json('realiz')] public ZReportTotalsSide $sales = new ZReportTotalsSide(),
        #[Json('return')] public ZReportTotalsSide $returns = new ZReportTotalsSide(),
        #[Json('service_in')] public Amount $serviceIn = new Amount('0.00'),
        #[Json('service_out')] public Amount $serviceOut = new Amount('0.00'),
    ) {}
}
