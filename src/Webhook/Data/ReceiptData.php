<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Webhook\Data;

use PrroCloud\Prro\Enum\ReceiptSource;
use PrroCloud\Prro\Enum\ReceiptType;
use PrroCloud\Prro\Internal\Attribute\Json;
use PrroCloud\Prro\Internal\Attribute\ListOf;
use PrroCloud\Prro\Model\ReturnOriginal;
use PrroCloud\Prro\Model\TaxTotal;
use PrroCloud\Prro\Money\Amount;

/**
 * Дані події receipt.registered: документ у тому вигляді, в якому його
 * отримала ДПС. Подія приходить на кожен зареєстрований чек — і поданий
 * через API, і поданий з панелі.
 */
final readonly class ReceiptData
{
    /**
     * @param string               $registerFiscalNumber фіскальний номер ПРРО
     * @param string               $documentId           документ у журналі сервісу
     * @param string               $taskId               завдання, яке зареєструвало документ
     * @param string               $userId               обліковий запис панелі, з якого подано чек; лише для
     *                                                   {@see ReceiptSource::Cabinet}
     * @param string               $cashier              ім'я касира, надруковане на чеку
     * @param string               $fiscalNumber         фіскальний номер документа; в офлайні — обчислений локально
     * @param bool                 $offline              чек подано в офлайн-сесії
     * @param bool                 $testing              тестовий документ: каса в режимі test
     * @param \DateTimeImmutable   $issuedAt             дата й час документа, як їх надруковано
     * @param Amount               $total                сума до сплати; для службових документів — сума руху готівки
     * @param Amount|null          $rounding             заокруглення готівки, якщо було
     * @param list<ReceiptLine>    $items                рядки чека
     * @param list<ReceiptPayment> $payments             форми оплати
     * @param list<TaxTotal>       $taxes                податки чека
     * @param string               $comment              коментар, надрукований на чеку
     * @param ReturnOriginal|null  $original             чек, який повертає повернення чи скасовує сторно
     * @param string               $receiptUrl           копія чека для покупця
     * @param string               $taxUrl               чек у кабінеті платника ДПС; порожній, доки офлайн-чек не
     *                                                   прийнято ДПС
     */
    public function __construct(
        #[Json('cash_register_id')] public string $cashRegisterId = '',
        #[Json('register_fiscal_number')] public string $registerFiscalNumber = '',
        #[Json('document_id')] public string $documentId = '',
        #[Json('task_id')] public string $taskId = '',
        #[Json('type')] public ReceiptType $type = ReceiptType::Unknown,
        #[Json('source')] public ReceiptSource $source = ReceiptSource::Unknown,
        #[Json('user_id')] public string $userId = '',
        #[Json('cashier')] public string $cashier = '',
        #[Json('local_number')] public int $localNumber = 0,
        #[Json('fiscal_number')] public string $fiscalNumber = '',
        #[Json('offline')] public bool $offline = false,
        #[Json('testing')] public bool $testing = false,
        #[Json('shift_id')] public string $shiftId = '',
        #[Json('shift_number')] public int $shiftNumber = 0,
        #[Json('issued_at')] public \DateTimeImmutable $issuedAt = new \DateTimeImmutable('@0'),
        #[Json('total')] public Amount $total = new Amount('0.00'),
        #[Json('rounding')] public ?Amount $rounding = null,
        #[Json('items'), ListOf(ReceiptLine::class)] public array $items = [],
        #[Json('payments'), ListOf(ReceiptPayment::class)] public array $payments = [],
        #[Json('taxes'), ListOf(TaxTotal::class)] public array $taxes = [],
        #[Json('comment')] public string $comment = '',
        #[Json('original')] public ?ReturnOriginal $original = null,
        #[Json('receipt_url')] public string $receiptUrl = '',
        #[Json('tax_url')] public string $taxUrl = '',
    ) {}
}
