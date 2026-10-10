<?php

// Повернення: чек повернення посилається на початковий чек продажу. Якщо
// той видано в PRRO.cloud, сервіс звіряє суму повернень із сумою продажу.
//
//     PRRO_TOKEN=… PRRO_REGISTER_ID=… PRRO_ORIGINAL_FN=… php examples/refund.php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use PrroCloud\Prro\Client;
use PrroCloud\Prro\Enum\PaymentType;
use PrroCloud\Prro\Enum\ReceiptType;
use PrroCloud\Prro\Input\Payment;
use PrroCloud\Prro\Input\ReceiptItem;
use PrroCloud\Prro\Input\ReceiptOriginal;
use PrroCloud\Prro\Input\SettlementReceipt;

$client = new Client(token: (string) getenv('PRRO_TOKEN'), baseUrl: (string) getenv('PRRO_BASE_URL'));

// Повернення понад суму продажу або сторнований початковий чек —
// TaskFailedException із fault->code у просторі return.*.
$task = $client->receipts->create(new SettlementReceipt(
    cashRegisterId: (string) getenv('PRRO_REGISTER_ID'),
    type: ReceiptType::Return,
    original: new ReceiptOriginal(fiscalNumber: (string) getenv('PRRO_ORIGINAL_FN')),
    comment: 'Повернення за замовленням 10412',
    items: [new ReceiptItem(name: 'Кава американо', quantity: '1', price: '65.00', taxLetters: 'А')],
    payments: [new Payment(type: PaymentType::Card, sum: '65.00')],
), idempotencyKey: 'refund-10412');

if (!$task->isDone()) {
    $task = $client->tasks->wait($task->id);
}

$r = $task->result?->receipt;
echo 'чек повернення: ', $r?->fiscalNumber, ' ', $r?->receiptUrl, "\n";
if ($r?->original !== null && !$r->original->verified) {
    echo "початковий чек видано поза PRRO.cloud — повернення прийнято без звірки\n";
}
