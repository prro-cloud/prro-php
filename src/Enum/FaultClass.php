<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Enum;

/**
 * Що може зарадити збою завдання.
 */
enum FaultClass: string
{
    /** Звірити стан із ДПС. */
    case Resync = 'resync';
    /** Виправити налаштування реєстратора. */
    case Config = 'config';
    /** Виконати пропущений попередній крок. */
    case Precursor = 'precursor';
    /** Потрібне рішення людини. */
    case UserAction = 'user_action';
    /** Тимчасова недоступність, допоможе повтор. */
    case Transient = 'transient';
    /** Виправити запит. */
    case Request = 'request';
    /** Збій сервісу. */
    case Internal = 'internal';
    /** Значення, якого ця версія бібліотеки не знає. */
    case Unknown = 'unknown';
}
