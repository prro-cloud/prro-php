<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Tests\Unit;

use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PrroCloud\Prro\Enum\AttentionReason;
use PrroCloud\Prro\Enum\DpsMode;
use PrroCloud\Prro\Enum\FaultClass;
use PrroCloud\Prro\Enum\PaymentType;
use PrroCloud\Prro\Enum\ReceiptSource;
use PrroCloud\Prro\Enum\ReceiptType;
use PrroCloud\Prro\Enum\TaskStatus;
use PrroCloud\Prro\Enum\WebhookEvent;
use PrroCloud\Prro\Internal\GoDuration;
use PrroCloud\Prro\Webhook\Event;
use PrroCloud\Prro\Webhook\InvalidSignatureException;
use PrroCloud\Prro\Webhook\PayloadTooLargeException;
use PrroCloud\Prro\Webhook\Webhook;
use PrroCloud\Prro\Webhook\WebhookException;

final class WebhookTest extends TestCase
{
    private const SECRET = 'whsec-test';

    /**
     * Будує тіло доставки події event із даними data.
     */
    private static function envelope(string $event, string $data): string
    {
        return sprintf('{"delivery_id":"0197a2c4-0000-7000-8000-000000000001","event":"%s",'
            . '"attempt":2,"sequence":42,"occurred_at":"2026-07-14T10:15:04Z","data":%s}', $event, $data);
    }

    public function testSignVerify(): void
    {
        $body = self::envelope('task.completed', '{}');
        $sig = Webhook::sign(self::SECRET, $body);
        self::assertMatchesRegularExpression('/^sha256=[0-9a-f]{64}$/', $sig);
        Webhook::verify(self::SECRET, $body, $sig);
        Webhook::verify(self::SECRET, $body, 'sha256=' . strtoupper(substr($sig, 7)));

        $bad = [
            'tampered body' => [self::SECRET, $sig, $body . ' '],
            'wrong secret' => ['other', $sig, $body],
            'no prefix' => [self::SECRET, substr($sig, 7), $body],
            'sha1 scheme' => [self::SECRET, 'sha1=' . substr($sig, 7), $body],
            'not hex' => [self::SECRET, 'sha256=zz', $body],
            'odd hex' => [self::SECRET, 'sha256=abc', $body],
            'empty' => [self::SECRET, '', $body],
            'truncated' => [self::SECRET, substr($sig, 0, 20), $body],
        ];
        foreach ($bad as $name => [$secret, $signature, $b]) {
            try {
                Webhook::verify($secret, $b, $signature);
                self::fail("{$name}: accepted");
            } catch (InvalidSignatureException) {
                self::addToAssertionCount(1);
            }
        }
        // Чужий підпис ніколи не проходить.
        for ($i = 0; $i < 200; $i++) {
            $forged = 'sha256=' . bin2hex(random_bytes(32));
            try {
                Webhook::verify(self::SECRET, $body, $forged);
                self::fail('forged signature accepted');
            } catch (InvalidSignatureException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testParse(): void
    {
        $body = self::envelope('shift.opened', '{"cash_register_id":"r1","shift_id":"s1","task_id":"t1","testing":true}');
        $e = Webhook::parse(self::SECRET, $body, Webhook::sign(self::SECRET, $body));
        self::assertSame('0197a2c4-0000-7000-8000-000000000001', $e->deliveryId);
        self::assertSame('shift.opened', $e->type);
        self::assertSame(WebhookEvent::ShiftOpened, $e->eventType());
        self::assertSame(2, $e->attempt);
        self::assertSame(42, $e->sequence);
        self::assertSame('2026-07-14T10:15:04+00:00', $e->occurredAt->format('c'));

        $d = $e->shift();
        self::assertSame('s1', $d->shiftId);
        self::assertTrue($d->testing);

        try {
            $e->task();
            self::fail('task() on shift.opened');
        } catch (\LogicException $ex) {
            self::assertStringContainsString('shift.opened', $ex->getMessage());
        }

        try {
            Webhook::parse(self::SECRET, '{', Webhook::sign(self::SECRET, '{'));
            self::fail('malformed envelope accepted');
        } catch (InvalidSignatureException) {
            self::fail('malformed envelope reported as bad signature');
        } catch (WebhookException) {
            self::addToAssertionCount(1);
        }
    }

    public function testUnknownEventType(): void
    {
        $body = self::envelope('register.renamed', '{"x":1}');
        $e = Webhook::parse(self::SECRET, $body, Webhook::sign(self::SECRET, $body));
        self::assertSame('register.renamed', $e->type);
        self::assertNull($e->eventType());
        self::assertSame(['x' => 1], $e->data);
        self::assertNull((new Event(type: 'unknown'))->eventType());
    }

    /**
     * @return iterable<string, array{string, string, \Closure(Event): void}>
     */
    public static function eventData(): iterable
    {
        yield 'task.completed' => ['task.completed', '{"task_id":"t1","type":"receipt","status":"succeeded","cash_register_id":"r1",
            "result":{"receipt":{"document_id":"d1","local_number":42,"fiscal_number":"7466800082",
            "register_fiscal_number":"4000000001","receipt_url":"https://r.prro.cloud/aB3xK9pQvT2mNr7c"}}}', static function (Event $e): void {
            $d = $e->task();
            self::assertSame(TaskStatus::Succeeded, $d->status);
            self::assertSame('7466800082', $d->result?->receipt?->fiscalNumber);
        }];
        yield 'task.failed' => ['task.failed', '{"task_id":"t1","type":"receipt","status":"failed","error":"rejected",
            "fault":{"code":"dps.check_local_number_invalid","class":"resync"}}', static function (Event $e): void {
            $d = $e->task();
            self::assertSame(FaultClass::Resync, $d->fault?->class);
            self::assertNull($d->result);
        }];
        yield 'key.expiring' => ['key.expiring', '{"key_id":"k1","cash_register_ids":["r1","r2"],"cert_subject":"ФОП Іваненко",
            "cert_valid_to":"2026-08-01T00:00:00Z"}', static function (Event $e): void {
            $d = $e->key();
            self::assertSame(['r1', 'r2'], $d->cashRegisterIds);
            self::assertSame('08', $d->certValidTo->format('m'));
        }];
        yield 'register.offline_limit' => ['register.offline_limit', '{"cash_register_id":"r1","session_id":"s1","dps_session_id":77,
            "started_at":"2026-07-13T00:00:00Z","session_used":"30h0m0s","month_used":"140h12m0s"}', static function (Event $e): void {
            $d = $e->offline();
            self::assertSame(30 * 3600, $d->sessionUsedSeconds);
            self::assertSame(140 * 3600 + 12 * 60, $d->monthUsedSeconds);
            self::assertSame(77, $d->dpsSessionId);
        }];
        yield 'register.online' => ['register.online', '{"cash_register_id":"r1","session_id":"s1","dps_session_id":77,
            "started_at":"2026-07-13T00:00:00Z","ended_at":"2026-07-13T02:00:00Z","documents":15}', static function (Event $e): void {
            $d = $e->offline();
            self::assertSame(15, $d->documents);
            self::assertNotNull($d->endedAt);
            self::assertSame(0, $d->sessionUsedSeconds);
        }];
        yield 'register.offline_abandoned' => ['register.offline_abandoned', '{"cash_register_id":"r1","session_id":"s1","dps_session_id":77,"task_id":"t9",
            "documents":[{"document_id":"d1","class":"check","kind":"sale","local_number":45,"total":"65.00","created_at":"2026-07-13T01:00:00Z"}]}', static function (Event $e): void {
            $d = $e->offlineAbandoned();
            self::assertCount(1, $d->documents);
            self::assertSame('65.00', (string) $d->documents[0]->total);
        }];
        yield 'register.remediated' => ['register.remediated', '{"cash_register_id":"r1","task_id":"t1","action":"local_number_synced",
            "fault_code":"dps.check_local_number_invalid","details":{"old":41,"new":43}}', static function (Event $e): void {
            $d = $e->remediation();
            self::assertSame('local_number_synced', $d->action);
            self::assertSame(43, $d->details['new']);
        }];
        yield 'register.needs_attention' => ['register.needs_attention', '{"cash_register_id":"r1","reason":"offline_submission_paused","session_id":"s1","error":"rejected"}', static function (Event $e): void {
            $d = $e->attention();
            self::assertSame(AttentionReason::OfflineSubmissionPaused, $d->reason);
            self::assertSame('s1', $d->sessionId);
        }];
        yield 'client.balance_low' => ['client.balance_low', '{"balance":"5000.0000","soft_limit":"0.0000","price_per_receipt":"50.0000",
            "receipts_left":100,"threshold_receipts":200}', static function (Event $e): void {
            $d = $e->balance();
            self::assertSame(100, $d->receiptsLeft);
            self::assertSame('50.0000', (string) $d->pricePerReceipt);
        }];
        yield 'receipt.registered' => ['receipt.registered', '{"cash_register_id":"r1","register_fiscal_number":"4000000001",
            "document_id":"d2","task_id":"t2","type":"return","source":"cabinet","user_id":"u1","cashier":"Олена",
            "local_number":43,"fiscal_number":"7466800083","offline":false,"testing":false,"shift_id":"s1",
            "shift_number":147,"issued_at":"2026-07-14T10:15:04.123456789Z","total":"65.00",
            "items":[{"name":"Кава американо","quantity":"1","price":"65.00","cost":"65.00","tax_letters":"А"}],
            "payments":[{"type":"card","name":"КАРТКА","sum":"65.00"},{"type":"crypto","name":"КРИПТА","sum":"0.00"}],
            "taxes":[{"letter":"А","name":"ПДВ","rate":"20.00","turnover":"65.00","sum":"10.83"}],
            "original":{"fiscal_number":"7466800082","document_id":"d1","verified":true},
            "receipt_url":"https://r.prro.cloud/aB3xK9pQvT2mNr7d"}', static function (Event $e): void {
            $d = $e->receipt();
            self::assertSame(ReceiptType::Return, $d->type);
            self::assertSame(ReceiptSource::Cabinet, $d->source);
            self::assertSame(147, $d->shiftNumber);
            self::assertSame('65.00', (string) $d->items[0]->price);
            self::assertNull($d->items[0]->discount);
            self::assertSame(PaymentType::Card, $d->payments[0]->type);
            self::assertSame(PaymentType::Unknown, $d->payments[1]->type);
            self::assertSame('10.83', (string) $d->taxes[0]->sum);
            self::assertTrue($d->original?->verified);
            self::assertNull($d->rounding);
            self::assertSame('15:04.123456', $d->issuedAt->format('i:s.u'));
        }];
        yield 'system.dps_offline' => ['system.dps_offline', '{"verdict":"offline","override":"","effective":"offline","cause":"probe_failed",
            "occurred_at":"2026-07-14T10:15:04Z"}', static function (Event $e): void {
            self::assertSame(DpsMode::Offline, $e->dps()->effective);
        }];
        yield 'webhook.ping' => ['webhook.ping', '{"message":"test","webhook_id":"w1","sent_at":"2026-07-14T10:15:04Z"}', static function (Event $e): void {
            self::assertSame('w1', $e->ping()->webhookId);
        }];
    }

    /**
     * @param \Closure(Event): void $check
     */
    #[DataProvider('eventData')]
    public function testEventData(string $event, string $data, \Closure $check): void
    {
        $body = self::envelope($event, $data);
        $check(Webhook::parse(self::SECRET, $body, Webhook::sign(self::SECRET, $body)));
    }

    public function testOfflineDataBadDuration(): void
    {
        $e = new Event(type: 'register.offline_limit', data: ['session_used' => 'forever']);
        $this->expectException(WebhookException::class);
        $e->offline();
    }

    /**
     * @return iterable<array{string, int}>
     */
    public static function durations(): iterable
    {
        yield ['35h12m0s', 35 * 3600 + 12 * 60];
        yield ['0', 0];
        yield ['0s', 0];
        yield ['1.5h', 5400];
        yield ['2m30.9s', 150];
        yield ['300ms', 0];
        yield ['1500ms', 1];
        yield ['-1m', -60];
        yield ['+5s', 5];
        yield ['1h1m1s1ms1us1ns', 3661];
        yield ['3µs', 0];
        yield ['1.h', 3600]; // як у Go: цифр до крапки досить
    }

    #[DataProvider('durations')]
    public function testGoDuration(string $value, int $seconds): void
    {
        self::assertSame($seconds, GoDuration::toSeconds($value));
    }

    public function testGoDurationPrecision(): void
    {
        self::assertSame(1_001_001_001, GoDuration::toNanoseconds('1s1ms1us1ns'));
        self::assertSame(1_500_000, GoDuration::toNanoseconds('1.5ms'));
        foreach (['', '1', 'h', '1d', '.s', '1h-1m', 'forever', ' 1s'] as $bad) {
            try {
                GoDuration::toSeconds($bad);
                self::fail("accepted \"{$bad}\"");
            } catch (\UnexpectedValueException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testProcess(): void
    {
        $got = null;
        $handler = static function (Event $e) use (&$got): void {
            $got = $e;
            if ($e->eventType() === WebhookEvent::TaskFailed) {
                throw new \RuntimeException('db down');
            }
        };

        $ok = self::envelope('task.completed', '{"task_id":"t1","type":"receipt","status":"succeeded"}');
        $r = Webhook::process(self::SECRET, $handler, 'POST', $ok, Webhook::sign(self::SECRET, $ok));
        self::assertSame(200, $r->status);
        self::assertInstanceOf(Event::class, $got);
        self::assertSame('task.completed', $got->type);

        $failed = self::envelope('task.failed', '{}');
        $malformed = '{"event":';
        $huge = str_repeat('x', Webhook::MAX_BODY_BYTES + 1);
        $cases = [
            'handler error' => ['POST', $failed, Webhook::sign(self::SECRET, $failed), 500],
            'bad signature' => ['POST', $ok, Webhook::sign('other', $ok), 401],
            'no signature' => ['POST', $ok, '', 401],
            'malformed' => ['POST', $malformed, Webhook::sign(self::SECRET, $malformed), 400],
            'too large' => ['POST', $huge, Webhook::sign(self::SECRET, $huge), 413],
            'GET' => ['GET', '', '', 405],
        ];
        foreach ($cases as $name => [$method, $body, $sig, $want]) {
            $r = Webhook::process(self::SECRET, $handler, $method, $body, $sig);
            self::assertSame($want, $r->status, $name);
            self::assertNotSame('', $r->body, $name);
        }
        self::assertSame(['Allow' => 'POST'], Webhook::process(self::SECRET, $handler, 'GET', '', '')->headers);
        self::assertInstanceOf(\RuntimeException::class, Webhook::process(self::SECRET, $handler, 'POST', $failed, Webhook::sign(self::SECRET, $failed))->error);
    }

    public function testFromPsrRequest(): void
    {
        $body = self::envelope('webhook.ping', '{"message":"test","webhook_id":"w1","sent_at":"2026-07-14T10:15:04Z"}');
        $request = new ServerRequest('POST', '/prro-hook', ['X-Signature' => Webhook::sign(self::SECRET, $body)], $body);
        $request->getBody()->read(5); // тіло вже частково прочитано фреймворком

        self::assertSame('w1', Webhook::fromPsrRequest($request, self::SECRET)->ping()->webhookId);

        $huge = str_repeat('x', Webhook::MAX_BODY_BYTES + 1);
        $this->expectException(PayloadTooLargeException::class);
        Webhook::fromPsrRequest(new ServerRequest('POST', '/', [], $huge), self::SECRET);
    }
}
