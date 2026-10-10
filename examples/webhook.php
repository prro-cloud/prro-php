<?php

// Приймач вебхуків: перевіряє підпис, відкидає повтори доставок і реагує на
// події чеків, каси і балансу.
//
//     PRRO_WEBHOOK_SECRET=… php -S 0.0.0.0:8080 examples/webhook.php
//
// Адреса приймача для вебхука — http://<хост>:8080/prro-hook.

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use PrroCloud\Prro\Enum\WebhookEvent;
use PrroCloud\Prro\Webhook\Event;
use PrroCloud\Prro\Webhook\Webhook;

$uri = $_SERVER['REQUEST_URI'] ?? '';
if (!is_string($uri) || parse_url($uri, PHP_URL_PATH) !== '/prro-hook') {
    http_response_code(404);

    return;
}

/**
 * Відкидає повтори: доставка гарантована «щонайменше один раз». У
 * продакшені — таблиця з унікальним ключем delivery_id у тій самій
 * транзакції, що й обробка; тут — файл, бо кожен запит — новий процес PHP.
 */
function firstDelivery(string $deliveryId): bool
{
    $dir = sys_get_temp_dir() . '/prro-deliveries';
    if (!is_dir($dir)) {
        mkdir($dir, 0o700, true);
    }

    // Режим 'x' — атомарне «створити, якщо ще немає».
    $f = @fopen($dir . '/' . preg_replace('/[^0-9a-f-]/', '', $deliveryId), 'x');
    if ($f === false) {
        return false;
    }
    fclose($f);

    return true;
}

function logLine(string $format, string|int ...$args): void
{
    error_log(vsprintf($format, array_values($args)));
}

$result = Webhook::handle((string) getenv('PRRO_WEBHOOK_SECRET'), static function (Event $e): void {
    logLine('подія %s: доставка %s, спроба %d', $e->type, $e->deliveryId, $e->attempt);
    if (!firstDelivery($e->deliveryId)) {
        return;
    }

    switch ($e->eventType()) {
        case WebhookEvent::TaskCompleted:
            $r = $e->task()->result?->receipt;
            if ($r !== null) {
                logLine('чек %s видано: %s', $r->fiscalNumber, $r->receiptUrl);
            }
            break;
        case WebhookEvent::TaskFailed:
            $d = $e->task();
            logLine('завдання %s не виконано: %s', $d->taskId, $d->error);
            break;
        case WebhookEvent::RegisterOfflineLimit:
            $d = $e->offline();
            logLine('каса %s офлайн уже %d год — закрийте сесію', $d->cashRegisterId, intdiv($d->sessionUsedSeconds, 3600));
            break;
        case WebhookEvent::ClientBalanceLow:
            logLine('баланс добігає кінця: лишилося %d чеків', $e->balance()->receiptsLeft);
            break;
        case null:
            logLine('невідома подія %s — оновіть prro-php', $e->type);
            break;
        default:
            break;
    }
});

if ($result->error !== null) {
    logLine('доставку не прийнято (%d): %s', $result->status, $result->error->getMessage());
}
