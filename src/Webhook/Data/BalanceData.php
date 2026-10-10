<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Webhook\Data;

use PrroCloud\Prro\Internal\Attribute\Json;
use PrroCloud\Prro\Money\Kopecks;

/**
 * Дані події client.balance_low. Подія стосується клієнта, а не каси.
 */
final readonly class BalanceData
{
    /**
     * @param int $receiptsLeft      скільки чеків ще вміщує залишок (balance − softLimit)
     * @param int $thresholdReceipts поріг, за яким надіслано попередження
     */
    public function __construct(
        #[Json('balance')] public Kopecks $balance = new Kopecks('0'),
        #[Json('soft_limit')] public Kopecks $softLimit = new Kopecks('0'),
        #[Json('price_per_receipt')] public Kopecks $pricePerReceipt = new Kopecks('0'),
        #[Json('receipts_left')] public int $receiptsLeft = 0,
        #[Json('threshold_receipts')] public int $thresholdReceipts = 0,
    ) {}
}
