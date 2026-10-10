<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Service;

use PrroCloud\Prro\Enum\DeliveryStatus;
use PrroCloud\Prro\Enum\WebhookEvent;
use PrroCloud\Prro\Exception\InvalidArgumentException;
use PrroCloud\Prro\Internal\Hydrator;
use PrroCloud\Prro\Internal\Requester;
use PrroCloud\Prro\Model\CreatedWebhook;
use PrroCloud\Prro\Model\WebhookDelivery;
use PrroCloud\Prro\Model\WebhookEndpoint;
use PrroCloud\Prro\Model\WebhookList;

/**
 * Реєстрація вебхуків і історія доставок.
 */
final readonly class WebhooksService
{
    /** @internal */
    public function __construct(private Requester $requester) {}

    /**
     * Повертає вебхуки клієнта разом із лімітом: поки used < limit, можна
     * створити ще один.
     */
    public function list(): WebhookList
    {
        return Hydrator::hydrate(WebhookList::class, $this->requester->send('GET', '/v1/webhooks') ?? []);
    }

    /**
     * Реєструє вебхук. Доставка — POST із конвертом {delivery_id, event,
     * attempt, sequence, occurred_at, data} і підписом у заголовку
     * X-Signature; гарантія — «щонайменше один раз», тож приймач відкидає
     * повтори за delivery_id. Секрет у відповіді повертається рівно один
     * раз — збережіть його одразу.
     *
     * Вебхуків може бути не більше, ніж кас у клієнта, плюс один; призупинені
     * теж рахуються.
     *
     * @param string             $url    абсолютний http(s)-URL приймача
     * @param list<WebhookEvent> $events події підписки; порожній перелік — усі події
     * @param string|null        $secret секрет HMAC; null — згенерує сервер
     *
     * @throws \PrroCloud\Prro\Exception\WebhookLimitReachedException ліміт вебхуків вичерпано; limit і used — у details
     */
    public function create(string $url, array $events = [], ?string $secret = null): CreatedWebhook
    {
        $body = ['url' => $url];
        if ($events !== []) {
            $body['events'] = self::events($events);
        }
        if ($secret !== null && $secret !== '') {
            $body['secret'] = $secret;
        }

        return Hydrator::hydrate(CreatedWebhook::class, $this->requester->send('POST', '/v1/webhooks', body: $body) ?? []);
    }

    /**
     * Змінює URL, підписку або активність вебхука id; null — без змін. На
     * час ремонту свого приймача вимикайте вебхук (active: false), а не
     * видаляйте: видалення переводить недоставлене в dead.
     *
     * @param string|null             $url    новий абсолютний http(s)-URL приймача
     * @param list<WebhookEvent>|null $events новий перелік подій; [] — підписка на всі події
     * @param bool|null               $active false призупиняє доставку, не втрачаючи черги; true відновлює
     */
    public function update(string $id, ?string $url = null, ?array $events = null, ?bool $active = null): WebhookEndpoint
    {
        $path = Requester::path('/v1/webhooks/%s', $id);
        $body = [];
        if ($url !== null) {
            $body['url'] = $url;
        }
        if ($events !== null) {
            $body['events'] = self::events($events);
        }
        if ($active !== null) {
            $body['active'] = $active;
        }

        return Hydrator::hydrate(WebhookEndpoint::class, $this->requester->send('PATCH', $path, body: $body) ?? []);
    }

    /**
     * Видаляє вебхук id; недоставлені події переходять у dead.
     */
    public function delete(string $id): void
    {
        $this->requester->send('DELETE', Requester::path('/v1/webhooks/%s', $id), decode: false);
    }

    /**
     * Повертає історію доставок вебхука id, від найновішої. Доставлені
     * зберігаються 30 днів, невдалі (dead) — 14 днів від останньої спроби;
     * старіші зникають з історії.
     *
     * @param DeliveryStatus|null $status лише доставки в цьому стані; null — усі
     * @param int                 $limit  скільки доставок повернути: 1–200; 0 — типово 50
     *
     * @return list<WebhookDelivery>
     */
    public function deliveries(string $id, ?DeliveryStatus $status = null, int $limit = 0): array
    {
        if ($status === DeliveryStatus::Unknown) {
            throw new InvalidArgumentException('prro: deliveries: status Unknown is not a filter');
        }
        $query = [];
        if ($status !== null) {
            $query['status'] = $status->value;
        }
        if ($limit > 0) {
            $query['limit'] = (string) $limit;
        }
        $data = $this->requester->send('GET', Requester::path('/v1/webhooks/%s/deliveries', $id), $query);

        return Hydrator::hydrateList(WebhookDelivery::class, $data ?? [], 'deliveries');
    }

    /**
     * Повертає доставку deliveryId вебхука id у чергу з новим запасом
     * спроб; спроба — одразу. Доступно, поки доставка є в історії: невдала —
     * 14 днів від останньої спроби, далі — NotFoundException. Події,
     * доставлені тим часом, не повторюються: ця прийде після них, тож
     * упорядковуйте події за sequence. Якщо саме зараз триває спроба доставки — ApiException 409
     * з errorCode "delivery_in_flight".
     */
    public function replay(string $id, string $deliveryId): WebhookDelivery
    {
        $path = Requester::path('/v1/webhooks/%s/deliveries/%s/replay', $id, $deliveryId);

        return Hydrator::hydrate(WebhookDelivery::class, $this->requester->send('POST', $path) ?? []);
    }

    /**
     * @param array<mixed> $events
     *
     * @return list<string>
     */
    private static function events(array $events): array
    {
        $out = [];
        foreach ($events as $i => $e) {
            if (!$e instanceof WebhookEvent || $e === WebhookEvent::Unknown) {
                throw new InvalidArgumentException(sprintf('prro: events[%s] must be a known WebhookEvent', $i));
            }
            $out[] = $e->value;
        }

        return $out;
    }
}
