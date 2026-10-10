<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Model;

use PrroCloud\Prro\Internal\Attribute\Json;

/**
 * Документ, який видало завдання: номери в журналі та в ДПС і посилання
 * для покупця.
 */
final readonly class DocResult
{
    /**
     * @param string              $documentId           ідентифікатор документа в сервісі
     * @param int                 $localNumber          наскрізний номер документа в межах каси (ORDERNUM)
     * @param string              $fiscalNumber         фіскальний номер документа, присвоєний ДПС; в
     *                                                  офлайн-документа обчислений локально (<sid>.<n>.<crc>)
     * @param string              $registerFiscalNumber фіскальний номер каси («ФН ПРРО» на чеку); присутній
     *                                                  завжди, за ним інтеграція зіставляє записи з касою
     * @param bool                $offline              документ підписано в офлайн-сесії; у ДПС він
     *                                                  з'явиться після надсилання пакета сесії
     * @param string              $receiptUrl           копія чека для покупця (https://r.prro.cloud/<код>),
     *                                                  відкривається без авторизації. Параметр ?format= обирає
     *                                                  подання: html (типово), png (576 px для термопринтера
     *                                                  80 мм) або qr. Є лише в розрахункових документів
     * @param string              $taxUrl               той самий документ у кабінеті платника податків,
     *                                                  незалежне підтвердження фіскалізації. Порожній, доки
     *                                                  документ не потрапив до ДПС
     * @param ReturnOriginal|null $original             початковий чек; лише в документа повернення
     */
    public function __construct(
        #[Json('document_id')] public string $documentId = '',
        #[Json('local_number')] public int $localNumber = 0,
        #[Json('fiscal_number')] public string $fiscalNumber = '',
        #[Json('register_fiscal_number')] public string $registerFiscalNumber = '',
        #[Json('offline')] public bool $offline = false,
        #[Json('receipt_url')] public string $receiptUrl = '',
        #[Json('tax_url')] public string $taxUrl = '',
        #[Json('original')] public ?ReturnOriginal $original = null,
    ) {}
}
