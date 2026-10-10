<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Model;

use PrroCloud\Prro\Internal\Attribute\Json;
use PrroCloud\Prro\Internal\Attribute\ListOf;

/**
 * Вебхуки клієнта і ліміт їхньої кількості.
 */
final readonly class WebhookList
{
    /**
     * @param list<WebhookEndpoint> $webhooks точки доставки; секрети не повертаються
     * @param int                   $limit    скільки вебхуків дозволено: кількість кас клієнта плюс один
     * @param int                   $used     скільки вебхуків зареєстровано, включно з призупиненими
     */
    public function __construct(
        #[Json('webhooks'), ListOf(WebhookEndpoint::class)] public array $webhooks = [],
        #[Json('limit')] public int $limit = 0,
        #[Json('used')] public int $used = 0,
    ) {}
}
