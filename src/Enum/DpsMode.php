<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Enum;

/**
 * Режим роботи сервісу з ДПС.
 */
enum DpsMode: string
{
    /** ДПС відповідає вчасно. */
    case Online = 'online';
    /** ДПС відповідає, але повільно; буває лише у вердикті детектора. */
    case Degraded = 'degraded';
    /** ДПС недоступна, каси працюють в офлайн-сесіях. */
    case Offline = 'offline';
    /** Значення, якого ця версія бібліотеки не знає. */
    case Unknown = 'unknown';
}
