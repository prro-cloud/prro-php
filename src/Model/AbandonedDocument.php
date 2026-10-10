<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Model;

use PrroCloud\Prro\Enum\ReceiptType;
use PrroCloud\Prro\Internal\Attribute\Json;
use PrroCloud\Prro\Money\Amount;

/**
 * Документ відмовленої офлайн-сесії. Його фіскального номера в ДПС не
 * існує.
 */
final readonly class AbandonedDocument
{
    /**
     * @param string           $class        вид документа: check, zreport, open_shift, close_shift,
     *                                       offline_begin, offline_end
     * @param ReceiptType|null $kind         що оформив чек; лише для class = "check"
     * @param int              $localNumber  локальний номер, який мав документ
     * @param string           $fiscalNumber офлайн-фіскальний номер, надрукований на документі
     * @param Amount|null      $total        сума документа; лише для чеків
     * @param string           $payForm      форма оплати чека: cash, cashless або mixed
     */
    public function __construct(
        #[Json('document_id')] public string $documentId = '',
        #[Json('task_id')] public string $taskId = '',
        #[Json('class')] public string $class = '',
        #[Json('kind')] public ?ReceiptType $kind = null,
        #[Json('local_number')] public int $localNumber = 0,
        #[Json('fiscal_number')] public string $fiscalNumber = '',
        #[Json('total')] public ?Amount $total = null,
        #[Json('pay_form')] public string $payForm = '',
        #[Json('created_at')] public \DateTimeImmutable $createdAt = new \DateTimeImmutable('@0'),
    ) {}
}
