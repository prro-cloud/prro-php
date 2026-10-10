<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Input;

use PrroCloud\Prro\Enum\ReceiptType;
use PrroCloud\Prro\Exception\InvalidArgumentException;
use PrroCloud\Prro\Internal\Attribute\Json;
use PrroCloud\Prro\Internal\Hydrator;
use PrroCloud\Prro\Internal\Lists;
use PrroCloud\Prro\Money\Amount;

/**
 * Розрахунок за товари: реалізація, повернення або сторно. Рух готівки
 * повз продаж — {@see ServiceReceipt}: поля чужої форми API відхиляє з 422,
 * тож форми розділено на рівні класів.
 *
 *     new SettlementReceipt(
 *         cashRegisterId: $registerId,
 *         type: ReceiptType::Sale,
 *         items: [new ReceiptItem(name: 'Кава американо', quantity: '1', price: '65.00', taxLetters: 'А')],
 *         payments: [new Payment(type: PaymentType::Card, sum: '65.00')],
 *     );
 */
final readonly class SettlementReceipt implements \JsonSerializable
{
    /** @var list<ReceiptItem> */
    public array $items;

    /** @var list<Payment> */
    public array $payments;

    public ?\DateTimeImmutable $soldAt;

    public ?Amount $roundingStep;

    /**
     * @param string                  $cashRegisterId каса, на якій реєструють документ; обов'язкове
     * @param ReceiptType             $type           Sale, Return або Storno
     * @param list<ReceiptItem>       $items          позиції чека; щонайменше одна
     * @param list<Payment>           $payments       оплати чека; щонайменше одна
     * @param string|null             $cashier        ім'я касира, яке друкують у чеку; без нього рядка не буде
     * @param string|null             $comment        вільна примітка, яку друкують на чеку, — наприклад номер
     *                                                замовлення
     * @param \DateTimeInterface|null $soldAt         момент фактичного продажу, якщо документ дійшов до
     *                                                сервісу пізніше за касу. Друкується рядком «Продаж від
     *                                                03.09.2026 18:11», але фіскальним часом документа не
     *                                                стає. Час у майбутньому або старіший за 31 день — 422
     * @param ReceiptOriginal|null    $original       початковий чек; обов'язковий для повернення та сторно
     * @param Amount|string|null      $roundingStep   крок заокруглення готівки ("0.10" за правилами НБУ);
     *                                                null — без заокруглення
     *
     * @throws InvalidArgumentException вид документа не розрахунковий, сума має недійсний формат
     */
    public function __construct(
        #[Json('cash_register_id')] public string $cashRegisterId,
        #[Json('type')] public ReceiptType $type,
        #[Json('items')] array $items,
        #[Json('payments')] array $payments,
        #[Json('cashier')] public ?string $cashier = null,
        #[Json('comment')] public ?string $comment = null,
        #[Json('sold_at')] ?\DateTimeInterface $soldAt = null,
        #[Json('original')] public ?ReceiptOriginal $original = null,
        #[Json('rounding_step')] Amount|string|null $roundingStep = null,
    ) {
        if (!in_array($type, [ReceiptType::Sale, ReceiptType::Return, ReceiptType::Storno], true)) {
            throw new InvalidArgumentException(sprintf(
                'prro: SettlementReceipt: type must be sale, return or storno, got "%s"',
                $type->value,
            ));
        }
        $this->items = Lists::of($items, ReceiptItem::class, 'SettlementReceipt.items');
        $this->payments = Lists::of($payments, Payment::class, 'SettlementReceipt.payments');
        $this->soldAt = $soldAt === null ? null : \DateTimeImmutable::createFromInterface($soldAt);
        $this->roundingStep = $roundingStep === null ? null : Amount::of($roundingStep);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return Hydrator::dehydrate($this);
    }
}
