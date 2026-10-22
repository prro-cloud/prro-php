<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Input;

use PrroCloud\Prro\Enum\ReceiptType;
use PrroCloud\Prro\Exception\InvalidArgumentException;
use PrroCloud\Prro\Internal\Attribute\Json;
use PrroCloud\Prro\Internal\Hydrator;

/**
 * Сторнування попереднього чека: скасування чека продажу чи повернення,
 * зареєстрованого помилково. Позицій, оплат і суми не має — ДПС реєструє
 * сторно з нульовою сумою, а підсумки зміни й залишок готівки зменшуються на
 * суми скасованого чека.
 *
 * Сторнувати можна лише останній документ каси: чек продажу чи повернення,
 * виданий у PRRO.cloud і ще не сторнований. Інакше завдання завершується
 * збоєм із fault->code у просторі storno.*; для чека, після якого вже є інші
 * документи, оформлюють повернення ({@see SettlementReceipt}).
 *
 *     new StornoReceipt(cashRegisterId: $registerId, original: '7466800082', comment: 'Помилка касира');
 */
final readonly class StornoReceipt implements \JsonSerializable
{
    public StornoOriginal $original;

    /**
     * @param string                $cashRegisterId каса, на якій реєструють документ; обов'язкове
     * @param StornoOriginal|string $original       чек, який скасовують, або його фіскальний номер
     * @param string|null           $cashier        ім'я касира, яке друкують у чеку; без нього рядка не буде
     * @param string|null           $comment        вільна примітка, яку друкують на документі, — наприклад
     *                                              причина сторнування
     *
     * @throws InvalidArgumentException фіскальний номер порожній
     */
    public function __construct(
        #[Json('cash_register_id')] public string $cashRegisterId,
        #[Json('original')] StornoOriginal|string $original,
        #[Json('cashier')] public ?string $cashier = null,
        #[Json('comment')] public ?string $comment = null,
    ) {
        $this->original = is_string($original) ? new StornoOriginal($original) : $original;
        if ($this->original->fiscalNumber === '') {
            throw new InvalidArgumentException('prro: StornoReceipt: original fiscal number is empty');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        // Вид документа задає сам клас, тож окремого параметра type немає.
        return ['type' => ReceiptType::Storno->value] + Hydrator::dehydrate($this);
    }
}
