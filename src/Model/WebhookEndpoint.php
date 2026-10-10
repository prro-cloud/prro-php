<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Model;

use PrroCloud\Prro\Enum\WebhookEvent;
use PrroCloud\Prro\Internal\Attribute\Json;
use PrroCloud\Prro\Internal\Attribute\ListOf;

/**
 * Точка доставки подій: куди слати й на які саме.
 */
readonly class WebhookEndpoint
{
    /**
     * @param string             $url    абсолютний http(s)-URL приймача
     * @param list<WebhookEvent> $events події підписки; порожній перелік — усі події
     * @param bool               $active доставка ввімкнена; false призупиняє її, не втрачаючи черги
     */
    public function __construct(
        #[Json('id')] public string $id = '',
        #[Json('url')] public string $url = '',
        #[Json('events'), ListOf(WebhookEvent::class)] public array $events = [],
        #[Json('active')] public bool $active = false,
        #[Json('created_at')] public \DateTimeImmutable $createdAt = new \DateTimeImmutable('@0'),
        #[Json('updated_at')] public \DateTimeImmutable $updatedAt = new \DateTimeImmutable('@0'),
    ) {}
}
