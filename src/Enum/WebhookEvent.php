<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Enum;

/**
 * Тип події вебхука.
 */
enum WebhookEvent: string
{
    /** Завдання завершилося успішно. */
    case TaskCompleted = 'task.completed';
    /** Завдання завершилося помилкою. */
    case TaskFailed = 'task.failed';
    /** Зміну відкрито. */
    case ShiftOpened = 'shift.opened';
    /** Зміну закрито. */
    case ShiftClosed = 'shift.closed';
    /** Сертифікат ключа КЕП спливає протягом 30 днів; раз на добу. */
    case KeyExpiring = 'key.expiring';
    /** Каса перейшла в офлайн-сесію. */
    case RegisterOffline = 'register.offline';
    /** Каса повернулася онлайн. */
    case RegisterOnline = 'register.online';
    /** Наближення до законних меж офлайн-сесії (36 і 168 годин). */
    case RegisterOfflineLimit = 'register.offline_limit';
    /** Від офлайн-сесії відмовлено; у даних — документи, яких ДПС не отримала. */
    case RegisterOfflineAbandoned = 'register.offline_abandoned';
    /** Сервіс сам усунув розбіжність із ДПС. */
    case RegisterRemediated = 'register.remediated';
    /** Розбіжності не усуваються самі, на касу має поглянути людина. */
    case RegisterNeedsAttention = 'register.needs_attention';
    /** Баланс опустився нижче порога; попередження перед тим, як чеки почнуть відхилятися з 402. */
    case ClientBalanceLow = 'client.balance_low';

    // Зарезервовані події: на них не підписуються, але доставка може їх нести.

    /** Тестова відправка з кабінету. */
    case Ping = 'webhook.ping';
    /** Сервіс перейшов в офлайн-режим роботи з ДПС. */
    case DpsOffline = 'system.dps_offline';
    /** Сервіс повернувся в онлайн-режим роботи з ДПС. */
    case DpsOnline = 'system.dps_online';
    /** Режим роботи з ДПС часто перемикається. */
    case DpsFlapping = 'system.dps_flapping';
    /** Режим роботи з ДПС задано вручну. */
    case DpsModeChanged = 'system.dps_mode_changed';

    /** Подія, якої ця версія бібліотеки не знає; лише у відповідях. */
    case Unknown = 'unknown';

    /**
     * Чи можна підписатися на подію: зарезервовані події доставляються без
     * підписки.
     */
    public function isSubscribable(): bool
    {
        return match ($this) {
            self::Ping, self::DpsOffline, self::DpsOnline, self::DpsFlapping, self::DpsModeChanged, self::Unknown => false,
            default => true,
        };
    }
}
