<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Input;

use PrroCloud\Prro\Enum\ReceiptType;
use PrroCloud\Prro\Exception\InvalidArgumentException;
use PrroCloud\Prro\Internal\Attribute\Json;
use PrroCloud\Prro\Internal\Hydrator;
use PrroCloud\Prro\Money\Amount;

/**
 * Рух готівки повз продаж: службове внесення (ReceiptType::ServiceIn) або
 * інкасація (ReceiptType::ServiceOut). Позицій і оплат не має — лише суму.
 */
final readonly class ServiceReceipt implements \JsonSerializable
{
    public Amount $sum;

    public ?\DateTimeImmutable $soldAt;

    /**
     * @param string                  $cashRegisterId каса, на якій реєструють документ; обов'язкове
     * @param ReceiptType             $type           ServiceIn або ServiceOut
     * @param Amount|string           $sum            сума руху, додатна
     * @param string|null             $cashier        ім'я касира, яке друкують у документі
     * @param string|null             $comment        за що гроші: «Інкасація», «Розмінна монета»
     * @param \DateTimeInterface|null $soldAt         момент фактичної операції, якщо документ дійшов до сервісу
     *                                                пізніше; правила — як у SettlementReceipt::$soldAt
     *
     * @throws InvalidArgumentException вид документа не службовий, сума має недійсний формат
     */
    public function __construct(
        #[Json('cash_register_id')] public string $cashRegisterId,
        #[Json('type')] public ReceiptType $type,
        #[Json('sum')] Amount|string $sum,
        #[Json('cashier')] public ?string $cashier = null,
        #[Json('comment')] public ?string $comment = null,
        #[Json('sold_at')] ?\DateTimeInterface $soldAt = null,
    ) {
        if ($type !== ReceiptType::ServiceIn && $type !== ReceiptType::ServiceOut) {
            throw new InvalidArgumentException(sprintf(
                'prro: ServiceReceipt: type must be service_in or service_out, got "%s"',
                $type->value,
            ));
        }
        $this->sum = Amount::of($sum);
        $this->soldAt = $soldAt === null ? null : \DateTimeImmutable::createFromInterface($soldAt);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return Hydrator::dehydrate($this);
    }
}
