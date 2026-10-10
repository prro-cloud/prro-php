<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Webhook;

/**
 * Відповідь приймача на доставку: HTTP-статус і короткий текст.
 */
final readonly class HandlerResult
{
    /**
     * @param int                   $status  200 — подію оброблено; 401 — підпис недійсний; 405 — не POST;
     *                                       413 — тіло понад MAX_BODY_BYTES; 400 — конверт не розібрано;
     *                                       500 — обробник кинув виняток
     * @param string                $body    короткий текст відповіді
     * @param array<string, string> $headers додаткові заголовки відповіді
     * @param \Throwable|null       $error   причина відповіді не-2xx — для журналу
     */
    public function __construct(
        public int $status,
        public string $body = '',
        public array $headers = [],
        public ?\Throwable $error = null,
    ) {}
}
