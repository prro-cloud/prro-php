<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Webhook\Data;

use PrroCloud\Prro\Internal\Attribute\Json;

/**
 * Дані тестової відправки webhook.ping.
 */
final readonly class PingData
{
    public function __construct(
        #[Json('message')] public string $message = '',
        #[Json('webhook_id')] public string $webhookId = '',
        #[Json('sent_at')] public \DateTimeImmutable $sentAt = new \DateTimeImmutable('@0'),
    ) {}
}
