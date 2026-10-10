<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Tests\Contract;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PrroCloud\Prro\Client;
use PrroCloud\Prro\Enum\AttentionReason;
use PrroCloud\Prro\Enum\DeliveryStatus;
use PrroCloud\Prro\Enum\DpsMode;
use PrroCloud\Prro\Enum\FaultClass;
use PrroCloud\Prro\Enum\InvoiceStatus;
use PrroCloud\Prro\Enum\PaymentType;
use PrroCloud\Prro\Enum\ReceiptType;
use PrroCloud\Prro\Enum\RegisterMode;
use PrroCloud\Prro\Enum\RegisterState;
use PrroCloud\Prro\Enum\ShiftMode;
use PrroCloud\Prro\Enum\ShiftStatus;
use PrroCloud\Prro\Enum\TaskStatus;
use PrroCloud\Prro\Enum\TaskType;
use PrroCloud\Prro\Enum\WebhookEvent;
use PrroCloud\Prro\Input;
use PrroCloud\Prro\Internal\Hydrator;
use PrroCloud\Prro\Model;
use PrroCloud\Prro\Testing\MockTransport;
use Symfony\Component\Yaml\Yaml;

/**
 * Тести контракту звіряють клієнт із tests/testdata/openapi.yaml — копією
 * контракту сервісу (оновлюється через make sync-spec): кожна операція має
 * метод, кожне значення перелічення — case, кожне поле схеми — поле моделі,
 * і навпаки.
 */
final class ContractTest extends TestCase
{
    /** @var array<mixed>|null */
    private static ?array $spec = null;

    /**
     * @return array<mixed>
     */
    private static function spec(): array
    {
        if (self::$spec === null) {
            $spec = Yaml::parseFile(__DIR__ . '/../testdata/openapi.yaml');
            self::assertIsArray($spec);
            self::assertIsArray($spec['paths'] ?? null, 'openapi.yaml: no paths');
            self::$spec = $spec;
        }

        return self::$spec;
    }

    /**
     * Повертає вузол за шляхом ключів або null.
     *
     * @param array<mixed> $node
     * @param list<string> $path
     */
    private static function at(array $node, array $path): mixed
    {
        foreach ($path as $key) {
            if (!is_array($node) || !array_key_exists($key, $node)) {
                return null;
            }
            $node = $node[$key];
        }

        return $node;
    }

    public function testVersion(): void
    {
        self::assertSame(
            Client::API_VERSION,
            self::at(self::spec(), ['info', 'version']),
            'tests/testdata/openapi.yaml has a new version: review the contract changes and bump Client::API_VERSION',
        );
    }

    /**
     * operationId контракту → виклик клієнта. Ідентифікатори в шляху —
     * "ID1", "ID2".
     *
     * @return array<string, \Closure(Client): mixed>
     */
    private static function operations(): array
    {
        return [
            'getVersion' => static fn(Client $c) => $c->system->version(),
            'whoami' => static fn(Client $c) => $c->system->whoAmI(),
            'createReceipt' => static fn(Client $c) => $c->receipts->create(
                new Input\ServiceReceipt(cashRegisterId: 'ID1', type: ReceiptType::ServiceIn, sum: '1.00'),
            ),
            'getTask' => static fn(Client $c) => $c->tasks->get('ID1'),
            'listCashRegistersForToken' => static fn(Client $c) => $c->cashRegisters->list(),
            'getCashRegisterState' => static fn(Client $c) => $c->cashRegisters->get('ID1'),
            'openShift' => static fn(Client $c) => $c->shifts->open('ID1'),
            'closeShift' => static fn(Client $c) => $c->shifts->close('ID1'),
            'closeOfflineSession' => static fn(Client $c) => $c->offlineSessions->close('ID1'),
            'retryOfflineSubmission' => static fn(Client $c) => $c->offlineSessions->retry('ID1'),
            'abandonOfflineSession' => static fn(Client $c) => $c->offlineSessions->abandon('ID1'),
            'clearRegisterAttention' => static fn(Client $c) => $c->cashRegisters->clearAttention('ID1'),
            'setCashBalance' => static fn(Client $c) => $c->cashRegisters->setCashBalance('ID1', '0.00'),
            'billingUsage' => static fn(Client $c) => $c->billing->usage(),
            'listInvoices' => static fn(Client $c) => $c->invoices->list(),
            'listWebhooks' => static fn(Client $c) => $c->webhooks->list(),
            'createWebhook' => static fn(Client $c) => $c->webhooks->create('https://example.com'),
            'updateWebhook' => static fn(Client $c) => $c->webhooks->update('ID1'),
            'deleteWebhook' => static fn(Client $c) => $c->webhooks->delete('ID1'),
            'listWebhookDeliveries' => static fn(Client $c) => $c->webhooks->deliveries('ID1'),
            'replayWebhookDelivery' => static fn(Client $c) => $c->webhooks->replay('ID1', 'ID2'),
            'systemDPSStatus' => static fn(Client $c) => $c->system->dps(),
        ];
    }

    public function testOperations(): void
    {
        $paths = self::at(self::spec(), ['paths']);
        self::assertIsArray($paths);
        $operations = self::operations();
        $seen = [];
        $errors = [];

        foreach ($paths as $path => $item) {
            foreach (['get', 'post', 'put', 'patch', 'delete'] as $method) {
                $op = is_array($item) ? ($item[$method] ?? null) : null;
                if (!is_array($op)) {
                    continue;
                }
                $id = is_string($op['operationId'] ?? null) ? $op['operationId'] : '';
                $seen[$id] = true;
                $call = $operations[$id] ?? null;
                if ($call === null) {
                    $errors[] = sprintf('%s %s (%s): no client method', strtoupper($method), $path, $id);
                    continue;
                }

                // Шаблон шляху з ідентифікаторами ID1, ID2 по порядку.
                $n = 0;
                $want = strtoupper($method) . ' ' . preg_replace_callback('/\{[^}]+\}/', static function () use (&$n): string {
                    return 'ID' . ++$n;
                }, (string) $path);

                $mock = MockTransport::repeat(MockTransport::json(200, []));
                $call(new Client(token: 'tok', baseUrl: 'https://api.test', transport: $mock));
                $r = $mock->lastRequest();
                if ($r->method . ' ' . $r->path() !== $want) {
                    $errors[] = sprintf('%s: client sends %s %s, contract says %s', $id, $r->method, $r->path(), $want);
                }

                $fiscal = false;
                foreach (is_array($op['parameters'] ?? null) ? $op['parameters'] : [] as $p) {
                    if (is_array($p) && is_string($p['$ref'] ?? null) && str_ends_with($p['$ref'], '/IdempotencyKey')) {
                        $fiscal = true;
                    }
                }
                $sent = $r->header('Idempotency-Key') !== null;
                if ($sent !== $fiscal) {
                    $errors[] = sprintf('%s: Idempotency-Key sent = %s, contract requires = %s', $id, var_export($sent, true), var_export($fiscal, true));
                }
            }
        }
        foreach (array_keys($operations) as $id) {
            if (!isset($seen[$id])) {
                $errors[] = $id . ': client method for an operation the contract does not have';
            }
        }
        self::assertSame([], $errors);
        self::assertCount(22, $operations);
    }

    /**
     * @return iterable<string, array{list<string>, class-string<\BackedEnum>, list<string>}>
     */
    public static function enums(): iterable
    {
        // skip — значення контракту, які бібліотека свідомо не відображає.
        yield 'TaskType' => [['Task', 'properties', 'type'], TaskType::class, ['verify_key', 'dps_objects']];
        yield 'TaskStatus' => [['Task', 'properties', 'status'], TaskStatus::class, []];
        yield 'FaultClass' => [['Fault', 'properties', 'class'], FaultClass::class, []];
        yield 'PaymentType' => [['PaymentType'], PaymentType::class, []];
        yield 'ReceiptType' => [['AbandonedDocument', 'properties', 'kind'], ReceiptType::class, []];
        yield 'RegisterMode' => [['CashRegisterState', 'properties', 'mode'], RegisterMode::class, []];
        yield 'RegisterState' => [['CashRegisterState', 'properties', 'state'], RegisterState::class, []];
        yield 'ShiftMode' => [['ShiftMode'], ShiftMode::class, []];
        yield 'ShiftStatus' => [['CashRegisterState', 'properties', 'current_shift', 'properties', 'status'], ShiftStatus::class, []];
        yield 'AttentionReason' => [['CashRegisterState', 'properties', 'attention', 'properties', 'reason'], AttentionReason::class, []];
        yield 'DeliveryStatus' => [['WebhookDelivery', 'properties', 'status'], DeliveryStatus::class, []];
        yield 'InvoiceStatus' => [['Invoice', 'properties', 'status'], InvoiceStatus::class, []];
        yield 'DpsMode' => [['DPSPulse', 'properties', 'verdict'], DpsMode::class, []];
    }

    /**
     * @param list<string>              $path
     * @param class-string<\BackedEnum> $enum
     * @param list<string>              $skip
     */
    #[DataProvider('enums')]
    public function testEnums(array $path, string $enum, array $skip): void
    {
        $values = self::enumValues(['components', 'schemas', ...$path]);
        $values = array_values(array_diff($values, $skip));
        $cases = array_map(static fn(\BackedEnum $c): string => (string) $c->value, $enum::cases());
        self::assertEqualsCanonicalizing($values, array_values(array_diff($cases, ['unknown'])), $enum);
    }

    public function testReceiptTypeSplit(): void
    {
        self::assertEqualsCanonicalizing(['sale', 'return', 'storno'], self::enumValues(['components', 'schemas', 'SettlementReceiptInput', 'properties', 'type']));
        self::assertEqualsCanonicalizing(['service_in', 'service_out'], self::enumValues(['components', 'schemas', 'ServiceReceiptInput', 'properties', 'type']));
    }

    public function testWebhookEvents(): void
    {
        $subscribable = array_values(array_map(
            static fn(WebhookEvent $e): string => $e->value,
            array_filter(WebhookEvent::cases(), static fn(WebhookEvent $e): bool => $e->isSubscribable()),
        ));
        self::assertEqualsCanonicalizing(self::enumValues(['components', 'schemas', 'WebhookEvent']), $subscribable);
    }

    /**
     * @param list<string> $path
     *
     * @return list<string>
     */
    private static function enumValues(array $path): array
    {
        $values = self::at(self::spec(), [...$path, 'enum']);
        self::assertIsArray($values, implode('.', $path) . ': no enum in contract');

        return array_values(array_map(static fn(mixed $v): string => is_scalar($v) ? (string) $v : '', $values));
    }

    /**
     * @return iterable<string, array{list<string>, class-string, list<string>}>
     */
    public static function schemas(): iterable
    {
        $schema = self::schemaPath(...);
        // skip — поля контракту, які бібліотека свідомо не відображає.
        yield 'CashRegister' => [$schema('CashRegisterListItem'), Model\CashRegister::class, []];
        yield 'CashRegisterState' => [$schema('CashRegisterState'), Model\CashRegisterState::class, []];
        yield 'Shift' => [$schema('CashRegisterState', 'properties', 'current_shift'), Model\Shift::class, []];
        yield 'OfflineSession' => [$schema('CashRegisterState', 'properties', 'offline_session'), Model\OfflineSession::class, []];
        yield 'OfflineLimits' => [$schema('CashRegisterState', 'properties', 'offline_limits'), Model\OfflineLimits::class, []];
        yield 'Attention' => [$schema('CashRegisterState', 'properties', 'attention'), Model\Attention::class, []];
        yield 'CashBalance' => [$schema('CashBalance'), Model\CashBalance::class, []];
        yield 'ShiftTotals' => [$schema('ShiftTotals'), Model\ShiftTotals::class, []];
        yield 'ShiftTotalsSide' => [$schema('ShiftTotalsSide'), Model\ShiftTotalsSide::class, []];
        yield 'PayFormTotal' => [$schema('ShiftTotalsSide', 'properties', 'pay_forms', 'items'), Model\PayFormTotal::class, []];
        yield 'CashBalanceDeclaration' => [$schema('CashBalanceDeclaration'), Model\CashBalanceDeclaration::class, []];
        yield 'Task' => [$schema('Task'), Model\Task::class, []];
        yield 'Fault' => [$schema('Fault'), Model\Fault::class, []];
        // Результати завдань кабінету (перевірка ключа, об'єкти ДПС) машинному API не потрібні.
        yield 'TaskResult' => [$schema('TaskResult'), Model\TaskResult::class, ['key_verification', 'key_password_check', 'dps_objects']];
        yield 'DocResult' => [$schema('DocResult'), Model\DocResult::class, []];
        yield 'ReturnOriginal' => [$schema('ReturnOriginal'), Model\ReturnOriginal::class, []];
        yield 'OfflineAbandonResult' => [$schema('OfflineAbandonResult'), Model\OfflineAbandonResult::class, []];
        yield 'AbandonedDocument' => [$schema('AbandonedDocument'), Model\AbandonedDocument::class, []];
        yield 'ReconcileReport' => [$schema('ReconcileReport'), Model\ReconcileReport::class, []];
        yield 'ReconcileCash' => [$schema('ReconcileReport', 'properties', 'cash'), Model\ReconcileCash::class, []];
        yield 'ReconcileTotals' => [$schema('ReconcileReport', 'properties', 'totals'), Model\ReconcileTotals::class, []];
        yield 'ShiftTotalsBrief' => [$schema('ShiftTotalsBrief'), Model\ShiftTotalsBrief::class, []];
        // mode передається параметром запиту (async: true), а не полем тіла.
        yield 'SettlementReceipt' => [$schema('SettlementReceiptInput'), Input\SettlementReceipt::class, ['mode']];
        yield 'ReceiptOriginal' => [$schema('SettlementReceiptInput', 'properties', 'original'), Input\ReceiptOriginal::class, []];
        yield 'ServiceReceipt' => [$schema('ServiceReceiptInput'), Input\ServiceReceipt::class, ['mode']];
        yield 'ReceiptItem' => [$schema('ReceiptItem'), Input\ReceiptItem::class, []];
        yield 'Discount' => [$schema('ReceiptItem', 'properties', 'discount'), Input\Discount::class, []];
        yield 'Payment' => [$schema('ReceiptPayment'), Input\Payment::class, []];
        yield 'CardDetails' => [$schema('ReceiptPayment', 'properties', 'card'), Input\CardDetails::class, []];
        yield 'WebhookEndpoint' => [$schema('WebhookEndpoint'), Model\WebhookEndpoint::class, []];
        yield 'WebhookDelivery' => [$schema('WebhookDelivery'), Model\WebhookDelivery::class, []];
        yield 'BillingUsage' => [$schema('BillingUsage'), Model\BillingUsage::class, []];
        yield 'BillingPeriod' => [$schema('BillingUsage', 'properties', 'period'), Model\BillingPeriod::class, []];
        yield 'Tariff' => [$schema('BillingUsage', 'properties', 'tariff'), Model\Tariff::class, []];
        yield 'Invoice' => [$schema('Invoice'), Model\Invoice::class, []];
        yield 'DpsStatus' => [$schema('DPSPulse'), Model\DpsStatus::class, []];
        yield 'DpsTunables' => [$schema('DPSPulse', 'properties', 'tunables'), Model\DpsTunables::class, []];
        yield 'Identity' => [['paths', '/v1/whoami', 'get', 'responses', '200', 'content', 'application/json', 'schema'], Model\Identity::class, []];
    }

    /**
     * @return list<string>
     */
    private static function schemaPath(string ...$path): array
    {
        return ['components', 'schemas', ...array_values($path)];
    }

    /**
     * @param list<string> $path
     * @param class-string $class
     * @param list<string> $skip
     */
    #[DataProvider('schemas')]
    public function testSchemas(array $path, string $class, array $skip): void
    {
        self::assertFieldsMatch($path, Hydrator::fields($class), $skip, $class);
    }

    /**
     * Тіла запитів без власних класів: імена полів, які надсилає метод з усіма
     * необов'язковими параметрами.
     *
     * @return iterable<string, array{string, string, \Closure(Client): mixed, list<string>}>
     */
    public static function requestBodies(): iterable
    {
        yield 'openShift' => ['/v1/cash-registers/{id}/shifts', 'post', static fn(Client $c) => $c->shifts->open('r1', cashier: 'x'), ['mode']];
        yield 'closeOfflineSession' => ['/v1/cash-registers/{id}/offline-session/close', 'post', static fn(Client $c) => $c->offlineSessions->close('r1', cashier: 'x'), ['mode']];
        yield 'setCashBalance' => ['/v1/cash-registers/{id}/cash-balance', 'put', static fn(Client $c) => $c->cashRegisters->setCashBalance('r1', '1.00'), []];
        yield 'createWebhook' => ['/v1/webhooks', 'post', static fn(Client $c) => $c->webhooks->create('https://x', [WebhookEvent::TaskCompleted], 's'), []];
        yield 'updateWebhook' => ['/v1/webhooks/{id}', 'patch', static fn(Client $c) => $c->webhooks->update('w1', 'https://x', [], true), []];
    }

    /**
     * @param \Closure(Client): mixed $call
     * @param list<string>            $skip
     */
    #[DataProvider('requestBodies')]
    public function testRequestBodies(string $path, string $method, \Closure $call, array $skip): void
    {
        $mock = MockTransport::repeat(MockTransport::json(200, []));
        $call(new Client(token: 'tok', transport: $mock));
        $body = json_decode((string) $mock->lastRequest()->body, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($body);

        $schema = ['paths', $path, $method, 'requestBody', 'content', 'application/json', 'schema'];
        self::assertFieldsMatch($schema, array_map(strval(...), array_keys($body)), $skip, $method . ' ' . $path);
    }

    /**
     * @param list<string> $path
     * @param list<string> $fields
     * @param list<string> $skip
     */
    private static function assertFieldsMatch(array $path, array $fields, array $skip, string $name): void
    {
        $props = self::at(self::spec(), [...$path, 'properties']);
        self::assertIsArray($props, $name . ': no such schema with properties in contract');
        $props = array_values(array_diff(array_map(strval(...), array_keys($props)), $skip));

        self::assertSame([], array_values(array_diff($props, $fields)), $name . ': contract fields without a model field');
        self::assertSame([], array_values(array_diff($fields, $props)), $name . ': model fields not in the contract');
    }
}
