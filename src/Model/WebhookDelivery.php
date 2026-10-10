<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Model;

use PrroCloud\Prro\Enum\DeliveryStatus;
use PrroCloud\Prro\Enum\WebhookEvent;
use PrroCloud\Prro\Internal\Attribute\Json;

/**
 * Одна доставка події та її стан.
 */
final readonly class WebhookDelivery
{
    /**
     * @param string       $id             ідентифікатор доставки; його передають у webhooks->replay()
     * @param WebhookEvent $event          тип події; крім підписних, буває webhook.ping і system.dps_*
     * @param string       $cashRegisterId каса, якої стосується подія; порожній у подій рівня клієнта
     * @param int          $sequence       номер події в межах каси (поле sequence конверта); за ним
     *                                     упорядковують події. 0 у подій рівня клієнта
     * @param array<mixed> $data           корисне навантаження події
     * @param string       $lastError      чим завершилася остання невдала спроба
     */
    public function __construct(
        #[Json('id')] public string $id = '',
        #[Json('event')] public WebhookEvent $event = WebhookEvent::Unknown,
        #[Json('status')] public DeliveryStatus $status = DeliveryStatus::Unknown,
        #[Json('attempts')] public int $attempts = 0,
        #[Json('cash_register_id')] public string $cashRegisterId = '',
        #[Json('sequence')] public int $sequence = 0,
        #[Json('data')] public array $data = [],
        #[Json('last_error')] public string $lastError = '',
        #[Json('next_attempt_at')] public ?\DateTimeImmutable $nextAttemptAt = null,
        #[Json('created_at')] public \DateTimeImmutable $createdAt = new \DateTimeImmutable('@0'),
        #[Json('delivered_at')] public ?\DateTimeImmutable $deliveredAt = null,
    ) {}
}
