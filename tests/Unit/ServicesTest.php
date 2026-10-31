<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PrroCloud\Prro\Client;
use PrroCloud\Prro\Enum\AttentionReason;
use PrroCloud\Prro\Enum\DeliveryStatus;
use PrroCloud\Prro\Enum\FaultClass;
use PrroCloud\Prro\Enum\InvoiceStatus;
use PrroCloud\Prro\Enum\PaymentType;
use PrroCloud\Prro\Enum\ReceiptType;
use PrroCloud\Prro\Enum\RegisterState;
use PrroCloud\Prro\Enum\ShiftMode;
use PrroCloud\Prro\Enum\TaskStatus;
use PrroCloud\Prro\Enum\TaskType;
use PrroCloud\Prro\Enum\WebhookEvent;
use PrroCloud\Prro\Exception\ApiException;
use PrroCloud\Prro\Exception\ForbiddenException;
use PrroCloud\Prro\Exception\InvalidArgumentException;
use PrroCloud\Prro\Exception\TaskFailedException;
use PrroCloud\Prro\Exception\WaitTimeoutException;
use PrroCloud\Prro\Exception\WebhookLimitReachedException;
use PrroCloud\Prro\Http\Request;
use PrroCloud\Prro\Http\Response;
use PrroCloud\Prro\Input\CardDetails;
use PrroCloud\Prro\Input\Discount;
use PrroCloud\Prro\Input\Payment;
use PrroCloud\Prro\Input\ReceiptItem;
use PrroCloud\Prro\Input\ReceiptOriginal;
use PrroCloud\Prro\Input\ServiceReceipt;
use PrroCloud\Prro\Input\SettlementReceipt;
use PrroCloud\Prro\Input\StornoOriginal;
use PrroCloud\Prro\Input\StornoReceipt;
use PrroCloud\Prro\Money\Amount;
use PrroCloud\Prro\Service\InvoicesService;
use PrroCloud\Prro\Testing\MockTransport;
use PrroCloud\Prro\Tests\Support\ClientFactory;

final class ServicesTest extends TestCase
{
    use ClientFactory;

    private const UUID_V7 = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    private const TASK = '{"task_id":"t1","type":"open_shift","status":"succeeded","created_at":"2026-08-03T01:07:53Z"}';

    public function testReceiptsCreateSettlement(): void
    {
        [$client, $mock] = $this->stub(201, self::fixture('receipt_task'));

        $task = $client->receipts->create(new SettlementReceipt(
            cashRegisterId: 'reg-1',
            type: ReceiptType::Sale,
            items: [new ReceiptItem(name: 'Кава американо', quantity: '1', price: '65.00', taxLetters: 'А')],
            payments: [new Payment(type: PaymentType::Card, sum: Amount::fromKop(6500))],
        ), idempotencyKey: 'order-10412');

        $r = $mock->lastRequest();
        self::assertRequest($r, 'POST', '/v1/receipts');
        self::assertSame('order-10412', $r->header('Idempotency-Key'));
        self::assertSame(
            '{"cash_register_id":"reg-1","type":"sale",'
            . '"items":[{"name":"Кава американо","quantity":"1","price":"65.00","tax_letters":"А"}],'
            . '"payments":[{"type":"card","sum":"65.00"}]}',
            $r->body,
        );

        self::assertTrue($task->isDone());
        self::assertSame(TaskType::Receipt, $task->type);
        self::assertSame('2026-08-03T01:07:54.123456', $task->finishedAt?->format('Y-m-d\TH:i:s.u'), 'nanoseconds truncated');
        self::assertNotNull($task->result);
        self::assertSame(147, $task->result->shiftNumber);
        self::assertSame('reg-1', $task->result->cashRegisterId);
        $doc = $task->result->receipt;
        self::assertNotNull($doc);
        self::assertSame('7466800082', $doc->fiscalNumber);
        self::assertSame(42, $doc->localNumber);
        self::assertStringStartsWith('https://r.prro.cloud/', $doc->receiptUrl);
        self::assertNull($doc->original);
    }

    public function testReceiptsCreateService(): void
    {
        [$client, $mock] = $this->stub(202, '{"task_id":"t1","type":"receipt","status":"pending","created_at":"2026-08-03T01:07:53Z"}');

        $task = $client->receipts->create(new ServiceReceipt(
            cashRegisterId: 'reg-1',
            type: ReceiptType::ServiceOut,
            sum: '1500.00',
            comment: 'Інкасація',
        ), async: true);

        $r = $mock->lastRequest();
        self::assertRequest($r, 'POST', '/v1/receipts', 'mode=async');
        self::assertMatchesRegularExpression(self::UUID_V7, (string) $r->header('Idempotency-Key'));
        self::assertSame(
            ['cash_register_id' => 'reg-1', 'type' => 'service_out', 'sum' => '1500.00', 'comment' => 'Інкасація'],
            self::body($r),
        );
        self::assertFalse($task->isDone());
        self::assertSame(TaskStatus::Pending, $task->status);
    }

    public function testReceiptsCreateStorno(): void
    {
        [$client, $mock] = $this->stub(201, self::TASK);

        $client->receipts->create(new StornoReceipt(cashRegisterId: 'reg-1', original: '7466800082', comment: 'Помилка касира'));

        $r = $mock->lastRequest();
        self::assertRequest($r, 'POST', '/v1/receipts');
        self::assertSame(
            ['type' => 'storno', 'cash_register_id' => 'reg-1', 'original' => ['fiscal_number' => '7466800082'], 'comment' => 'Помилка касира'],
            self::body($r),
        );
        self::assertEquals(
            new StornoReceipt('reg-1', '7466800082'),
            new StornoReceipt('reg-1', new StornoOriginal('7466800082')),
        );
    }

    /**
     * @return iterable<string, array{\Closure(): object}>
     */
    public static function badReceipts(): iterable
    {
        $item = new ReceiptItem(name: 'x', quantity: 1, price: '1.00');
        $pay = new Payment(type: PaymentType::Cash, sum: '1.00');
        yield 'settlement as service' => [static fn() => new SettlementReceipt('r', ReceiptType::ServiceIn, [$item], [$pay])];
        // Сторно — окрема форма без позицій і оплат.
        yield 'settlement as storno' => [static fn() => new SettlementReceipt('r', ReceiptType::Storno, [$item], [$pay])];
        yield 'storno without original' => [static fn() => new StornoReceipt('r', '')];
        yield 'settlement unknown' => [static fn() => new SettlementReceipt('r', ReceiptType::Unknown, [$item], [$pay])];
        yield 'service as sale' => [static fn() => new ServiceReceipt('r', ReceiptType::Sale, '1.00')];
        yield 'service unknown' => [static fn() => new ServiceReceipt('r', ReceiptType::Unknown, '1.00')];
        // @phpstan-ignore argument.type (навмисно не той тип)
        yield 'item not an item' => [static fn() => new SettlementReceipt('r', ReceiptType::Sale, [$pay], [$pay])];
        yield 'three decimals' => [static fn() => new ReceiptItem(name: 'x', quantity: 1, price: '1.005')];
        // Так float потрапляє в суму у файлі без strict_types: 1/3 → "0.33333333333333".
        yield 'float artefact' => [static fn() => new Payment(type: PaymentType::Cash, sum: (string) (1 / 3))];
        yield 'bad date' => [static fn() => new ReceiptOriginal('1', date: '01.09.2026')];
        yield 'payment unknown' => [static fn() => new Payment(type: PaymentType::Unknown, sum: '1.00')];
    }

    /**
     * @param \Closure(): object $build
     */
    #[DataProvider('badReceipts')]
    public function testReceiptInputRejectedBeforeRequest(\Closure $build): void
    {
        $this->expectException(InvalidArgumentException::class);
        $build();
    }

    public function testReceiptReturnBody(): void
    {
        $receipt = new SettlementReceipt(
            cashRegisterId: 'reg-1',
            type: ReceiptType::Return,
            soldAt: new \DateTimeImmutable('2026-09-03 18:11:00', new \DateTimeZone('UTC')),
            original: new ReceiptOriginal(fiscalNumber: '7466800082', date: '2026-09-01'),
            items: [new ReceiptItem(
                name: 'Кава',
                quantity: '0.750',
                price: '65.00',
                discount: new Discount(sum: '5.00', percent: '10'),
                exciseLabels: ['AAAA1234'],
            )],
            payments: [new Payment(
                type: PaymentType::Card,
                sum: '60.00',
                means: 'ПЕРЕКАЗ З КАРТКИ',
                card: new CardDetails(
                    transactionDate: new \DateTime('2026-09-03 18:10:00', new \DateTimeZone('UTC')),
                    authCode: '123456',
                ),
            )],
            roundingStep: '0.10',
        );
        $json = json_encode($receipt, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        foreach ([
            '"sold_at":"2026-09-03T18:11:00Z"',
            '"original":{"fiscal_number":"7466800082","date":"2026-09-01"}',
            '"discount":{"sum":"5.00","percent":"10"}',
            '"excise_labels":["AAAA1234"]',
            '"card":{"transaction_date":"2026-09-03T18:10:00Z","auth_code":"123456"}',
            '"rounding_step":"0.10"',
        ] as $part) {
            self::assertStringContainsString($part, $json);
        }
        self::assertStringNotContainsString('null', $json);
    }

    public function testTasksWait(): void
    {
        $statuses = ['pending', 'processing', 'succeeded'];
        $mock = new MockTransport();
        foreach ($statuses as $s) {
            $mock->push(MockTransport::json(200, ['task_id' => 't1', 'type' => 'open_shift', 'status' => $s, 'created_at' => '2026-08-03T01:07:53Z']));
        }
        $client = $this->client($mock);

        $task = $client->tasks->wait('t1');

        self::assertSame(TaskStatus::Succeeded, $task->status);
        foreach ($mock->requests() as $r) {
            self::assertRequest($r, 'GET', '/v1/tasks/t1');
        }
        self::assertSame([0.5, 0.75], $this->clock->sleeps, 'interval grows ×1.5');
    }

    public function testTasksWaitIntervalCapped(): void
    {
        $mock = MockTransport::repeat(MockTransport::json(200, ['task_id' => 't1', 'status' => 'processing']));
        $client = $this->client($mock);
        try {
            $client->tasks->wait('t1', timeout: 30);
        } catch (WaitTimeoutException) {
        }
        self::assertSame(5.0, max([0.0, ...$this->clock->sleeps]));
        self::assertEqualsWithDelta(30.0, array_sum($this->clock->sleeps), 1e-9, 'sleeps never overshoot the deadline');
    }

    public function testTasksWaitFailed(): void
    {
        [$client] = $this->stub(200, '{"task_id":"t1","type":"receipt","status":"failed","created_at":"2026-08-03T01:07:53Z",
            "error":"DPS rejected","fault":{"code":"dps.check_local_number_invalid","class":"resync"}}');
        try {
            $client->tasks->wait('t1');
            self::fail('no exception');
        } catch (TaskFailedException $e) {
            self::assertSame(422, $e->statusCode);
            self::assertSame('task_failed', $e->errorCode);
            self::assertSame('t1', $e->taskId);
            self::assertSame(FaultClass::Resync, $e->fault?->class);
            self::assertSame(TaskStatus::Failed, $e->task?->status);
            self::assertSame('prro: 422 task_failed (dps.check_local_number_invalid): DPS rejected', $e->getMessage());
        }
    }

    public function testTasksWaitTimeout(): void
    {
        [$client, $mock] = $this->stub(200, '{"task_id":"t1","type":"receipt","status":"processing","created_at":"2026-08-03T01:07:53Z"}');
        try {
            $client->tasks->wait('t1', timeout: 2.0);
            self::fail('no exception');
        } catch (WaitTimeoutException $e) {
            self::assertSame(TaskStatus::Processing, $e->lastTask?->status);
        }
        self::assertGreaterThan(1, count($mock->requests()));
    }

    public function testEmptyIdAndEscaping(): void
    {
        [$client, $mock] = $this->stub(200, '{"id":"a/b","fiscal_number":"1","local_number":"1","mode":"test","state":"closed",
            "offline_limits":{"session_seconds":129600,"month_seconds":604800,"enforced":true}}');
        foreach ([
            static fn(Client $c): mixed => $c->tasks->get(''),
            static fn(Client $c): mixed => $c->webhooks->replay('w1', ''),
            static fn(Client $c): mixed => $c->shifts->open(''),
        ] as $call) {
            try {
                $call($client);
                self::fail('no exception for empty id');
            } catch (InvalidArgumentException $e) {
                self::assertSame('prro: empty id', $e->getMessage());
            }
        }
        self::assertSame([], $mock->requests(), 'request sent for empty id');

        $state = $client->cashRegisters->get('a/b');
        self::assertRequest($mock->lastRequest(), 'GET', '/v1/cash-registers/a%2Fb');
        self::assertSame(129600, $state->offlineLimits->sessionSeconds);
    }

    public function testCashRegisters(): void
    {
        [$client, $mock] = $this->stub(200, '{"cash_registers":[{"id":"reg-1","fiscal_number":"4000000001","local_number":"1",
            "org_name":"ФОП Іваненко","mode":"production","state":"opened"}]}');
        $list = $client->cashRegisters->list();
        self::assertRequest($mock->lastRequest(), 'GET', '/v1/cash-registers');
        self::assertCount(1, $list);
        self::assertSame(RegisterState::Opened, $list[0]->state);
        self::assertSame('ФОП Іваненко', $list[0]->orgName);

        [$client, $mock] = $this->stub(200, self::fixture('cash_register_state'));
        $st = $client->cashRegisters->get('reg-1');
        self::assertRequest($mock->lastRequest(), 'GET', '/v1/cash-registers/reg-1');
        self::assertSame(ShiftMode::RoundTheClock, $st->shiftMode);
        self::assertSame(['09:00', '21:00'], $st->shiftCloseTimes);
        self::assertSame(147, $st->currentShift?->number);
        self::assertSame('1500.00', (string) $st->cashBalance?->amount);
        self::assertSame(150000, $st->cashBalance?->amount->kop());
        self::assertSame('ГОТІВКА', $st->shiftTotals?->sales->payForms[0]->name);
        self::assertSame('0.00', (string) $st->shiftTotals->returns->sum);
        self::assertSame(36 * 3600, $st->offlineLimits->sessionSeconds);
        self::assertSame(AttentionReason::AutoCloseFailing, $st->attention?->reason);
        self::assertNull($st->offlineSession);
        self::assertTrue($st->offlineLimits->enforced);
        self::assertNull($st->offlineLimits->restoredAt);

        [$client] = $this->stub(200, '{
            "id":"reg-1","fiscal_number":"4000000001","local_number":"1","mode":"production","state":"offline",
            "offline_session":{"id":"os1","dps_session_id":77,"started_at":"2026-08-01T00:00:00Z","documents":40,
                "submit_paused_at":"2026-08-02T10:00:00Z",
                "submit_fault":{"code":"dps.offline_package_rejected","class":"user_action","message":"Пакет відхилено"}},
            "offline_limits":{"session_seconds":129600,"month_seconds":604800,"enforced":false,
                "restored_at":"2026-07-01T00:00:00Z"},
            "offline_month_used_seconds":180000,
            "attention":{"reason":"offline_submission_paused","since":"2026-08-02T10:00:00Z"}}');
        $st = $client->cashRegisters->get('reg-1');
        $sess = $st->offlineSession;
        self::assertNotNull($sess);
        self::assertSame('2026-08-02T10:00:00+00:00', $sess->submitPausedAt?->format(DATE_ATOM));
        self::assertSame(FaultClass::UserAction, $sess->submitFault?->class);
        self::assertSame('dps.offline_package_rejected', $sess->submitFault->code);
        self::assertFalse($st->offlineLimits->enforced);
        self::assertSame('2026-07-01', $st->offlineLimits->restoredAt?->format('Y-m-d'));
        self::assertSame(AttentionReason::OfflineSubmissionPaused, $st->attention?->reason);

        [$client, $mock] = $this->stub(200, '{"cleared":true}');
        self::assertTrue($client->cashRegisters->clearAttention('reg-1'));
        self::assertRequest($mock->lastRequest(), 'DELETE', '/v1/cash-registers/reg-1/attention');

        [$client, $mock] = $this->stub(200, '{"amount":"1500.00","previous":"-20.00","adjustment":"1520.00"}');
        $decl = $client->cashRegisters->setCashBalance('reg-1', '1500.00');
        self::assertSame('1520.00', (string) $decl->adjustment);
        self::assertSame(-2000, $decl->previous->kop());
        $r = $mock->lastRequest();
        self::assertRequest($r, 'PUT', '/v1/cash-registers/reg-1/cash-balance');
        self::assertSame('{"amount":"1500.00"}', $r->body);
        self::assertNull($r->header('Idempotency-Key'));
    }

    public function testSetCashBalanceRetriesAsPut(): void
    {
        $mock = new MockTransport(MockTransport::networkError(), MockTransport::json(200, ['amount' => '1.00', 'previous' => '0.00', 'adjustment' => '1.00']));
        $this->client($mock)->cashRegisters->setCashBalance('reg-1', '1.00');
        self::assertCount(2, $mock->requests(), 'PUT is idempotent and retried');
    }

    /**
     * @return iterable<string, array{\Closure(Client): mixed, string, string, ?string}>
     */
    public static function fiscalCalls(): iterable
    {
        yield 'open without cashier' => [static fn(Client $c) => $c->shifts->open('r1'), 'POST', '/v1/cash-registers/r1/shifts', null];
        yield 'open with cashier' => [
            static fn(Client $c) => $c->shifts->open('r1', cashier: 'Каса самообслуговування'),
            'POST', '/v1/cash-registers/r1/shifts', '{"cashier":"Каса самообслуговування"}',
        ];
        yield 'close' => [static fn(Client $c) => $c->shifts->close('r1'), 'DELETE', '/v1/cash-registers/r1/shifts/current', null];
        yield 'offline close' => [
            static fn(Client $c) => $c->offlineSessions->close('r1', cashier: 'Олена'),
            'POST', '/v1/cash-registers/r1/offline-session/close', '{"cashier":"Олена"}',
        ];
        yield 'offline close without cashier' => [
            static fn(Client $c) => $c->offlineSessions->close('r1'),
            'POST', '/v1/cash-registers/r1/offline-session/close', null,
        ];
        yield 'offline abandon' => [static fn(Client $c) => $c->offlineSessions->abandon('r1'), 'POST', '/v1/cash-registers/r1/offline-session/abandon', null];
    }

    /**
     * @param \Closure(Client): mixed $call
     */
    #[DataProvider('fiscalCalls')]
    public function testShiftsAndOfflineSessions(\Closure $call, string $method, string $path, ?string $body): void
    {
        [$client, $mock] = $this->stub(201, self::TASK);
        $call($client);
        $r = $mock->lastRequest();
        self::assertRequest($r, $method, $path);
        self::assertSame($body, $r->body);
        self::assertNotNull($r->header('Idempotency-Key'));
    }

    public function testOfflineRetry(): void
    {
        [$client, $mock] = $this->stub(200, '{"resumed_sessions":2}');
        self::assertSame(2, $client->offlineSessions->retry('r1'));
        $r = $mock->lastRequest();
        self::assertRequest($r, 'POST', '/v1/cash-registers/r1/offline-session/retry');
        self::assertNull($r->header('Idempotency-Key'));
    }

    public function testOfflineAbandonResult(): void
    {
        [$client] = $this->stub(201, self::fixture('offline_abandon_task'));
        $ab = $client->offlineSessions->abandon('r1')->result?->offlineAbandon;
        self::assertNotNull($ab);
        self::assertTrue($ab->closed);
        self::assertSame(77, $ab->dpsSessionId);
        self::assertCount(1, $ab->abandoned);
        self::assertSame(ReceiptType::Sale, $ab->abandoned[0]->kind);
        self::assertSame('65.00', (string) $ab->abandoned[0]->total);
    }

    public function testBillingUsage(): void
    {
        [$client, $mock] = $this->stub(200, self::fixture('billing_usage'));
        $u = $client->billing->usage('2026-07-01', new \DateTimeImmutable('2026-08-01'));
        self::assertRequest($mock->lastRequest(), 'GET', '/v1/billing/usage', 'from=2026-07-01&to=2026-08-01');
        self::assertSame('150000.0000', (string) $u->balance);
        self::assertSame(1200, $u->receipts);
        self::assertSame('50.0000', (string) $u->tariff->pricePerReceipt);
        self::assertSame('2026-08-01', $u->period->to);

        [$client, $mock] = $this->stub(200, '{"balance":"0","soft_limit":"0","blocked":true,"period":{"from":"2026-10-01","to":"2026-11-01"},
            "receipts":0,"charged":"0","tariff":{"plan_id":"p1","price_per_receipt":"50"}}');
        self::assertTrue($client->billing->usage()->blocked);
        self::assertRequest($mock->lastRequest(), 'GET', '/v1/billing/usage');
    }

    private static function invoicesPage(int $n, int $from): string
    {
        $items = [];
        for ($i = 0; $i < $n; $i++) {
            $items[] = sprintf('{"id":"inv-%d","period_start":"2026-01-01","period_end":"2026-01-31",'
                . '"amount":"100.0000","currency":"UAH","status":"paid","created_at":"2026-02-01T00:00:00Z"}', $from + $i);
        }

        return '{"invoices":[' . implode(',', $items) . ']}';
    }

    public function testInvoices(): void
    {
        [$client, $mock] = $this->stub(200, self::invoicesPage(2, 0));
        $list = $client->invoices->list(limit: 2, offset: 4);
        self::assertRequest($mock->lastRequest(), 'GET', '/v1/invoices', 'limit=2&offset=4');
        self::assertCount(2, $list);
        self::assertSame(InvoiceStatus::Paid, $list[0]->status);
        self::assertSame('2026-01-31', $list[0]->periodEnd);

        $pager = static function (Request $r): Response {
            parse_str($r->query(), $q);
            $offset = is_string($q['offset'] ?? null) ? (int) $q['offset'] : 0;

            return MockTransport::json(200, self::invoicesPage($offset === 0 ? InvoicesService::MAX_PAGE_SIZE : 5, $offset));
        };
        $mock = MockTransport::repeat($pager);
        $client = $this->client($mock);
        $ids = [];
        foreach ($client->invoices->all() as $inv) {
            $ids[] = $inv->id;
        }
        self::assertCount(205, $ids);
        self::assertSame('inv-200', $ids[200]);
        self::assertSame(['limit=200', 'limit=200&offset=200'], array_map(static fn(Request $r) => $r->query(), $mock->requests()));

        // Перерваний обхід не тягне наступну сторінку.
        foreach ($client->invoices->all() as $inv) {
            break;
        }
        self::assertCount(3, $mock->requests());

        [$client] = $this->stub(403, '{"code":"forbidden","message":"restricted token"}');
        $this->expectException(ForbiddenException::class);
        iterator_to_array($client->invoices->all());
    }

    public function testWebhooks(): void
    {
        $endpoint = self::fixture('webhook_endpoint');

        [$client, $mock] = $this->stub(200, '{"webhooks":[' . $endpoint . '],"limit":3,"used":1}');
        $list = $client->webhooks->list();
        self::assertRequest($mock->lastRequest(), 'GET', '/v1/webhooks');
        self::assertSame([WebhookEvent::TaskCompleted], $list->webhooks[0]->events);
        self::assertSame(3, $list->limit);
        self::assertSame(1, $list->used);

        [$client, $mock] = $this->stub(201, (string) preg_replace('/"id"/', '"secret":"s3cr3t","id"', $endpoint, 1));
        $created = $client->webhooks->create('https://example.com/prro-hook', [WebhookEvent::TaskCompleted]);
        self::assertSame('s3cr3t', $created->secret);
        self::assertSame('w1', $created->id);
        self::assertTrue($created->active);
        $r = $mock->lastRequest();
        self::assertRequest($r, 'POST', '/v1/webhooks');
        self::assertSame('{"url":"https://example.com/prro-hook","events":["task.completed"]}', $r->body);
        self::assertNull($r->header('Idempotency-Key'));

        [$client] = $this->stub(409, '{"code":"webhook_limit_reached","message":"limit","details":{"limit":3,"used":3}}');
        try {
            $client->webhooks->create('https://example.com/prro-hook');
            self::fail('no exception');
        } catch (WebhookLimitReachedException $e) {
            self::assertSame(['limit' => 3, 'used' => 3], $e->details);
        }

        foreach ([
            '{"active":false}' => static fn(Client $c) => $c->webhooks->update('w1', active: false),
            '{"events":[]}' => static fn(Client $c) => $c->webhooks->update('w1', events: []),
            '{"url":"https://example.com/new"}' => static fn(Client $c) => $c->webhooks->update('w1', url: 'https://example.com/new'),
            '{}' => static fn(Client $c) => $c->webhooks->update('w1'),
        ] as $want => $call) {
            [$client, $mock] = $this->stub(200, $endpoint);
            $call($client);
            self::assertRequest($mock->lastRequest(), 'PATCH', '/v1/webhooks/w1');
            self::assertSame($want, $mock->lastRequest()->body);
        }

        [$client, $mock] = $this->stub(204);
        $client->webhooks->delete('w1');
        self::assertRequest($mock->lastRequest(), 'DELETE', '/v1/webhooks/w1');

        [$client, $mock] = $this->stub(200, '{"deliveries":[{"id":"d1","event":"task.failed","status":"dead","attempts":8,
            "cash_register_id":"r1","sequence":42,"data":{"task_id":"t1","status":"failed"},"last_error":"HTTP 500",
            "created_at":"2026-07-14T10:15:04Z"}]}');
        $ds = $client->webhooks->deliveries('w1', DeliveryStatus::Dead, limit: 10);
        self::assertRequest($mock->lastRequest(), 'GET', '/v1/webhooks/w1/deliveries', 'limit=10&status=dead');
        self::assertSame(DeliveryStatus::Dead, $ds[0]->status);
        self::assertSame(['task_id' => 't1', 'status' => 'failed'], $ds[0]->data);
        self::assertSame(42, $ds[0]->sequence);

        [$client, $mock] = $this->stub(409, '{"code":"delivery_in_flight","message":"attempt in progress"}');
        try {
            $client->webhooks->replay('w1', 'd1');
            self::fail('no exception');
        } catch (ApiException $e) {
            self::assertSame(ApiException::class, $e::class);
            self::assertSame('delivery_in_flight', $e->errorCode);
        }
        self::assertRequest($mock->lastRequest(), 'POST', '/v1/webhooks/w1/deliveries/d1/replay');
    }

    public function testWebhookEventsValidated(): void
    {
        [$client, $mock] = $this->stub(201, '{}');
        foreach ([
            static fn() => $client->webhooks->create('https://x', [WebhookEvent::Unknown]),
            // @phpstan-ignore argument.type (навмисно рядок замість case)
            static fn() => $client->webhooks->create('https://x', ['task.completed']),
            static fn() => $client->webhooks->update('w1', events: [WebhookEvent::Unknown]),
            static fn() => $client->webhooks->deliveries('w1', DeliveryStatus::Unknown),
        ] as $call) {
            try {
                $call();
                self::fail('no exception');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
        self::assertSame([], $mock->requests());
    }

    public function testUnknownValuesAndFieldsDoNotBreakDecoding(): void
    {
        [$client] = $this->stub(200, '{"task_id":"t1","type":"brand_new_type","status":"paused","created_at":"2026-08-03T01:07:53Z",
            "new_field":{"nested":[1,2,3]},"fault":{"code":"x.y","class":"cosmic_rays","new":1}}');
        $task = $client->tasks->get('t1');
        self::assertSame(TaskType::Unknown, $task->type);
        self::assertSame(TaskStatus::Unknown, $task->status);
        self::assertSame(FaultClass::Unknown, $task->fault?->class);
        self::assertFalse($task->isDone());
    }

    public function testEmptyObjectDecodesToDefaults(): void
    {
        [$client] = $this->stub(200, '{}');
        $state = $client->cashRegisters->get('r1');
        self::assertSame('', $state->id);
        self::assertSame(0, $state->offlineLimits->monthSeconds);
        self::assertSame([], $client->cashRegisters->list());
    }
}
