<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Input;

use PrroCloud\Prro\Internal\Attribute\Json;
use PrroCloud\Prro\Internal\DateTimes;
use PrroCloud\Prro\Internal\Hydrator;

/**
 * Посилання на початковий чек повернення.
 */
final readonly class ReceiptOriginal implements \JsonSerializable
{
    /** Дата початкового чека, РРРР-ММ-ДД. */
    public ?string $date;

    /**
     * @param string                         $fiscalNumber         фіскальний номер початкового чека
     * @param string|null                    $registerFiscalNumber фіскальний номер каси початкового чека, якщо
     *                                                             це інша каса; без нього чек шукають на касі
     *                                                             повернення
     * @param \DateTimeInterface|string|null $date                 дата початкового чека: рядок РРРР-ММ-ДД або
     *                                                             дата в її часовому поясі
     */
    public function __construct(
        #[Json('fiscal_number')] public string $fiscalNumber,
        #[Json('register_fiscal_number')] public ?string $registerFiscalNumber = null,
        #[Json('date')] \DateTimeInterface|string|null $date = null,
    ) {
        $this->date = $date === null ? null : DateTimes::date($date, 'ReceiptOriginal.date');
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return Hydrator::dehydrate($this);
    }
}
