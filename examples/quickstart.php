<?php

// Швидкий старт: перший чек на касі в режимі test — безкоштовно і без
// реєстрації в податковій.
//
//     PRRO_TOKEN=… php examples/quickstart.php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use PrroCloud\Prro\Client;
use PrroCloud\Prro\Enum\PaymentType;
use PrroCloud\Prro\Enum\ReceiptType;
use PrroCloud\Prro\Enum\ShiftMode;
use PrroCloud\Prro\Exception\PaymentRequiredException;
use PrroCloud\Prro\Exception\TaskFailedException;
use PrroCloud\Prro\Input\Payment;
use PrroCloud\Prro\Input\ReceiptItem;
use PrroCloud\Prro\Input\SettlementReceipt;

$client = new Client(
    token: (string) getenv('PRRO_TOKEN'),
    baseUrl: (string) getenv('PRRO_BASE_URL'),
    userAgent: 'prro-quickstart/1.0',
);

// 1. Ідентифікатор каси — разовий виклик; збережіть id у налаштуваннях.
$registers = $client->cashRegisters->list();
if ($registers === []) {
    fwrite(STDERR, "токен не бачить жодної каси\n");
    exit(1);
}
$reg = $registers[0];
printf("каса %s (ФН %s), режим %s\n", $reg->id, $reg->fiscalNumber, $reg->mode->value);

// 2. Зміна: у режимі manual її відкривають явно, інакше перший чек
// відкриє її сам.
$state = $client->cashRegisters->get($reg->id);
if ($state->shiftMode === ShiftMode::Manual && $state->currentShift === null) {
    $client->shifts->open($reg->id, cashier: 'Каса 1');
}

// 3. Чек. Ключ ідемпотентності — від замовлення: повтор не створить
// другого чека.
try {
    $task = $client->receipts->create(new SettlementReceipt(
        cashRegisterId: $reg->id,
        type: ReceiptType::Sale,
        items: [new ReceiptItem(name: 'Кава американо', quantity: '1', price: '65.00', taxLetters: 'А')],
        payments: [new Payment(type: PaymentType::Card, sum: '65.00')],
    ), idempotencyKey: 'quickstart-order-1');
} catch (PaymentRequiredException) {
    fwrite(STDERR, "баланс вичерпано — поповніть рахунок у кабінеті\n");
    exit(1);
} catch (TaskFailedException $e) {
    // fault->code — стабільний код для обробки, fault->message — текст для людини.
    fwrite(STDERR, sprintf("чек не зареєстровано: %s (%s)\n", $e->fault->message ?? $e->errorMessage, $e->fault->code ?? $e->errorCode));
    exit(1);
}

// ДПС не встигла за 30 секунд — дочікуємося завдання.
if (!$task->isDone()) {
    $task = $client->tasks->wait($task->id);
}

$r = $task->result?->receipt;
if ($r === null) {
    fwrite(STDERR, "завдання {$task->id} без чека\n");
    exit(1);
}
echo 'фіскальний номер: ', $r->fiscalNumber, "\n";
echo 'чек для покупця:  ', $r->receiptUrl, "\n";
echo 'перевірка в ДПС:  ', $r->taxUrl, "\n";
