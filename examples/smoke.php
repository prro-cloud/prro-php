<?php

// Димова перевірка: викликає всі маршрути читання API і нічого не створює.
// Зручно, щоб перевірити токен і доступ або бібліотеку проти локального
// сервера. Код завершення 0 — усі перевірки пройдено.
//
//     PRRO_TOKEN=… php examples/smoke.php
//     PRRO_TOKEN=… PRRO_BASE_URL=http://localhost:8083 php examples/smoke.php
//     PRRO_DEBUG=1 … — журнал запитів у stderr

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use PrroCloud\Prro\Client;
use PrroCloud\Prro\Exception\ForbiddenException;
use Psr\Log\AbstractLogger;

$logger = null;
if (getenv('PRRO_DEBUG') !== false && interface_exists(Psr\Log\LoggerInterface::class)) {
    $logger = new class extends AbstractLogger {
        public function log($level, string|Stringable $message, array $context = []): void
        {
            fwrite(STDERR, $message . ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) . "\n");
        }
    };
}
$client = new Client(token: (string) getenv('PRRO_TOKEN'), baseUrl: (string) getenv('PRRO_BASE_URL'), logger: $logger);

$failed = 0;

/**
 * @param callable(): string $call повертає опис результату
 */
function check(string $name, callable $call, int &$failed): bool
{
    try {
        printf("✓ %-22s %s\n", $name, $call());

        return true;
    } catch (ForbiddenException) {
        printf("– %-22s пропущено: токен обмежено касами\n", $name);
    } catch (Throwable $e) {
        $failed++;
        printf("✗ %-22s %s\n", $name, $e->getMessage());
    }

    return false;
}

check('system->version', static fn() => $client->system->version(), $failed);

$ok = check('system->whoAmI', static function () use ($client): string {
    $me = $client->system->whoAmI();

    return sprintf('клієнт %s, усі каси: %s', $me->clientId, $me->allCashRegisters ? 'так' : 'ні');
}, $failed);
if (!$ok) {
    echo "без дійсного токена далі перевіряти нічого\n";
    exit(1);
}

check('system->dps', static function () use ($client): string {
    $dps = $client->system->dps();

    return sprintf('%s (вердикт %s)', $dps->effective->value, $dps->verdict->value);
}, $failed);

$registers = [];
check('cashRegisters->list', static function () use ($client, &$registers): string {
    $registers = $client->cashRegisters->list();

    return count($registers) . ' кас';
}, $failed);
foreach ($registers as $reg) {
    check('cashRegisters->get', static function () use ($client, $reg): string {
        $st = $client->cashRegisters->get($reg->id);
        $detail = sprintf('%s: %s, зміна %s, режим змін %s', $reg->fiscalNumber, $st->mode->value, $st->state->value, $st->shiftMode->value ?? '—');
        if ($st->cashBalance !== null) {
            $detail .= ', готівка ' . $st->cashBalance->amount;
        }

        return $detail;
    }, $failed);
}

check('billing->usage', static function () use ($client): string {
    $u = $client->billing->usage();

    return sprintf('баланс %s коп., чеків за період %d', $u->balance, $u->receipts);
}, $failed);
check('invoices->list', static fn() => count($client->invoices->list()) . ' рахунків', $failed);
check('webhooks->list', static fn() => count($client->webhooks->list()) . ' вебхуків', $failed);

if ($failed > 0) {
    echo "\nпомилок: {$failed}\n";
    exit(1);
}
