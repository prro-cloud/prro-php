<?php

// Асинхронний режим: виклик повертає завдання одразу, не тримаючи з'єднання
// до відповіді ДПС, а результат забирають опитуванням (або вебхуком —
// див. examples/webhook.php). Так варто робити на оформленні замовлення в
// веб-запиті.
//
//     PRRO_TOKEN=… PRRO_REGISTER_ID=… php examples/async.php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use PrroCloud\Prro\Client;
use PrroCloud\Prro\Enum\ReceiptType;
use PrroCloud\Prro\Exception\WaitTimeoutException;
use PrroCloud\Prro\Input\ServiceReceipt;
use PrroCloud\Prro\Money\Amount;

$client = new Client(token: (string) getenv('PRRO_TOKEN'), baseUrl: (string) getenv('PRRO_BASE_URL'));

$task = $client->receipts->create(new ServiceReceipt(
    cashRegisterId: (string) getenv('PRRO_REGISTER_ID'),
    type: ReceiptType::ServiceIn,
    sum: Amount::fromKop(50000), // "500.00"
    comment: 'Розмінна монета',
), idempotencyKey: 'float-' . date('Y-m-d'), async: true);
printf("завдання %s: %s\n", $task->id, $task->status->value);

try {
    $done = $client->tasks->wait($task->id, timeout: 120);
} catch (WaitTimeoutException) {
    fwrite(STDERR, "завдання {$task->id} ще виконується — перевірте пізніше\n");
    exit(1);
}
echo 'внесення зареєстровано: ', $done->result?->receipt?->fiscalNumber, "\n";
