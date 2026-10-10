<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Model;

use PrroCloud\Prro\Enum\WebhookEvent;
use PrroCloud\Prro\Internal\Attribute\Json;
use PrroCloud\Prro\Internal\Attribute\ListOf;

/**
 * Щойно створена точка доставки разом із секретом.
 */
final readonly class CreatedWebhook extends WebhookEndpoint
{
    /**
     * @param list<WebhookEvent> $events
     * @param string             $secret секрет HMAC для перевірки підпису доставок. Повертається рівно один
     *                                   раз — збережіть його одразу
     */
    public function __construct(
        #[Json('id')] string $id = '',
        #[Json('url')] string $url = '',
        #[Json('events'), ListOf(WebhookEvent::class)] array $events = [],
        #[Json('active')] bool $active = false,
        #[Json('created_at')] \DateTimeImmutable $createdAt = new \DateTimeImmutable('@0'),
        #[Json('updated_at')] \DateTimeImmutable $updatedAt = new \DateTimeImmutable('@0'),
        #[Json('secret')] public string $secret = '',
    ) {
        parent::__construct($id, $url, $events, $active, $createdAt, $updatedAt);
    }
}
