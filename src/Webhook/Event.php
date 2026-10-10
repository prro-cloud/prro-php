<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Webhook;

use PrroCloud\Prro\Enum\WebhookEvent;
use PrroCloud\Prro\Exception\DecodeException;
use PrroCloud\Prro\Internal\Attribute\Json;
use PrroCloud\Prro\Internal\Hydrator;
use PrroCloud\Prro\Webhook\Data\AttentionData;
use PrroCloud\Prro\Webhook\Data\BalanceData;
use PrroCloud\Prro\Webhook\Data\DpsData;
use PrroCloud\Prro\Webhook\Data\KeyData;
use PrroCloud\Prro\Webhook\Data\OfflineAbandonedData;
use PrroCloud\Prro\Webhook\Data\OfflineData;
use PrroCloud\Prro\Webhook\Data\PingData;
use PrroCloud\Prro\Webhook\Data\ReceiptData;
use PrroCloud\Prro\Webhook\Data\RemediationData;
use PrroCloud\Prro\Webhook\Data\ShiftData;
use PrroCloud\Prro\Webhook\Data\TaskData;

/**
 * Конверт доставки події.
 *
 * Тип події — сирий рядок {@see $type}: подію, якої ця версія бібліотеки
 * не знає, можна записати в журнал і пропустити. Типізовано його повертає
 * {@see eventType()}, а дані — методи {@see task()}, {@see shift()} тощо.
 */
final readonly class Event
{
    /**
     * @param string             $deliveryId ідентифікатор доставки; однаковий в усіх повторах, тож за ним
     *                                       відкидають дублікати
     * @param string             $type       тип події: "task.completed", …
     * @param int                $attempt    номер спроби доставки, від 1
     * @param int                $sequence   номер події в межах каси: зростає, пропуски можливі; за ним
     *                                       упорядковують події каси. 0 у подій рівня клієнта
     * @param \DateTimeImmutable $occurredAt коли подія сталася
     * @param array<mixed>       $data       корисне навантаження як є
     */
    public function __construct(
        #[Json('delivery_id')] public string $deliveryId = '',
        #[Json('event')] public string $type = '',
        #[Json('attempt')] public int $attempt = 0,
        #[Json('sequence')] public int $sequence = 0,
        #[Json('occurred_at')] public \DateTimeImmutable $occurredAt = new \DateTimeImmutable('@0'),
        #[Json('data')] public array $data = [],
    ) {}

    /**
     * Повертає тип події або null, якщо ця версія бібліотеки його не знає.
     */
    public function eventType(): ?WebhookEvent
    {
        $type = WebhookEvent::tryFrom($this->type);

        return $type === WebhookEvent::Unknown ? null : $type;
    }

    /**
     * Дані подій task.completed і task.failed.
     */
    public function task(): TaskData
    {
        return $this->decode(TaskData::class, WebhookEvent::TaskCompleted, WebhookEvent::TaskFailed);
    }

    /**
     * Дані подій shift.opened і shift.closed.
     */
    public function shift(): ShiftData
    {
        return $this->decode(ShiftData::class, WebhookEvent::ShiftOpened, WebhookEvent::ShiftClosed);
    }

    /**
     * Дані події key.expiring.
     */
    public function key(): KeyData
    {
        return $this->decode(KeyData::class, WebhookEvent::KeyExpiring);
    }

    /**
     * Дані подій register.offline, register.online і register.offline_limit.
     */
    public function offline(): OfflineData
    {
        return $this->decode(
            OfflineData::class,
            WebhookEvent::RegisterOffline,
            WebhookEvent::RegisterOnline,
            WebhookEvent::RegisterOfflineLimit,
        );
    }

    /**
     * Дані події register.offline_abandoned.
     */
    public function offlineAbandoned(): OfflineAbandonedData
    {
        return $this->decode(OfflineAbandonedData::class, WebhookEvent::RegisterOfflineAbandoned);
    }

    /**
     * Дані події register.remediated.
     */
    public function remediation(): RemediationData
    {
        return $this->decode(RemediationData::class, WebhookEvent::RegisterRemediated);
    }

    /**
     * Дані події register.needs_attention.
     */
    public function attention(): AttentionData
    {
        return $this->decode(AttentionData::class, WebhookEvent::RegisterNeedsAttention);
    }

    /**
     * Дані події client.balance_low.
     */
    public function balance(): BalanceData
    {
        return $this->decode(BalanceData::class, WebhookEvent::ClientBalanceLow);
    }

    /**
     * Дані подій system.dps_*.
     */
    public function dps(): DpsData
    {
        return $this->decode(
            DpsData::class,
            WebhookEvent::DpsOffline,
            WebhookEvent::DpsOnline,
            WebhookEvent::DpsFlapping,
            WebhookEvent::DpsModeChanged,
        );
    }

    /**
     * Дані події receipt.registered.
     */
    public function receipt(): ReceiptData
    {
        return $this->decode(ReceiptData::class, WebhookEvent::ReceiptRegistered);
    }

    /**
     * Дані тестової відправки webhook.ping.
     */
    public function ping(): PingData
    {
        return $this->decode(PingData::class, WebhookEvent::Ping);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     *
     * @throws \LogicException  подія іншого типу не несе таких даних
     * @throws WebhookException дані не розібрано
     */
    private function decode(string $class, WebhookEvent ...$types): object
    {
        if (!in_array($this->eventType(), $types, true)) {
            throw new \LogicException(sprintf('webhook: event "%s" does not carry %s', $this->type, $class));
        }
        try {
            return Hydrator::hydrate($class, $this->data);
        } catch (DecodeException $e) {
            throw new WebhookException(sprintf('webhook: decode %s data: %s', $this->type, $e->getMessage()), 0, $e);
        }
    }
}
