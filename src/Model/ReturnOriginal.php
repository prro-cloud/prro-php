<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Model;

use PrroCloud\Prro\Internal\Attribute\Json;

/**
 * Початковий чек повернення.
 */
final readonly class ReturnOriginal
{
    /**
     * @param string $fiscalNumber фіскальний номер початкового чека
     * @param string $documentId   початковий чек у журналі сервісу; порожній, якщо чек видано поза сервісом
     * @param bool   $verified     повернення звірено із залишком за початковим чеком; false — чек видано
     *                             іншим ПРРО, повернення прийнято без звірки
     */
    public function __construct(
        #[Json('fiscal_number')] public string $fiscalNumber = '',
        #[Json('document_id')] public string $documentId = '',
        #[Json('verified')] public bool $verified = false,
    ) {}
}
