<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Input;

use PrroCloud\Prro\Internal\Attribute\Json;
use PrroCloud\Prro\Internal\Hydrator;
use PrroCloud\Prro\Money\Amount;

/**
 * Реквізити еквайрингу. Поля acquirer* описують еквайра торговця, а не
 * самого торговця.
 */
final readonly class CardDetails implements \JsonSerializable
{
    public ?\DateTimeImmutable $transactionDate;

    public ?Amount $commission;

    /**
     * @param string|null             $systemName        назва платіжної системи
     * @param string|null             $acquirerId        ідентифікатор еквайра
     * @param string|null             $acquirerTaxId     податковий номер еквайра
     * @param string|null             $acquirerName      найменування еквайра
     * @param \DateTimeInterface|null $transactionDate   дата й час транзакції на платіжному пристрої
     * @param string|null             $transactionNumber номер транзакції на платіжному пристрої
     * @param string|null             $deviceId          ідентифікатор платіжного пристрою
     * @param string|null             $epzDetails        замаскований номер картки
     * @param string|null             $authCode          код авторизації
     * @param Amount|string|null      $commission        комісія еквайра, яку платить платник; у звичайному
     *                                                   продажу лишається порожньою
     */
    public function __construct(
        #[Json('system_name')] public ?string $systemName = null,
        #[Json('acquirer_id')] public ?string $acquirerId = null,
        #[Json('acquirer_tax_id')] public ?string $acquirerTaxId = null,
        #[Json('acquirer_name')] public ?string $acquirerName = null,
        #[Json('transaction_date')] ?\DateTimeInterface $transactionDate = null,
        #[Json('transaction_number')] public ?string $transactionNumber = null,
        #[Json('device_id')] public ?string $deviceId = null,
        #[Json('epz_details')] public ?string $epzDetails = null,
        #[Json('auth_code')] public ?string $authCode = null,
        #[Json('commission')] Amount|string|null $commission = null,
    ) {
        $this->transactionDate = $transactionDate === null ? null : \DateTimeImmutable::createFromInterface($transactionDate);
        $this->commission = $commission === null ? null : Amount::of($commission);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return Hydrator::dehydrate($this);
    }
}
