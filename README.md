# prro-php

[![CI](https://github.com/prro-cloud/prro-php/actions/workflows/ci.yml/badge.svg)](https://github.com/prro-cloud/prro-php/actions/workflows/ci.yml)

PHP-клієнт для API [PRRO.cloud](https://prro.cloud): фіскалізація чеків
програмного РРО, зміни, офлайн-сесії, білінг і вебхуки.

- Без залежностей — лише `ext-curl` і `ext-json`; вбудовується у
  WordPress-плагін без конфліктів із чужими версіями Guzzle.
- Безпечні повтори: ключ ідемпотентності генерується автоматично, мережеві
  збої та 5xx повторюються з експоненційною затримкою.
- Типізовані винятки і структурований опис збою ДПС.
- Вебхуки: перевірка підпису, типізовані події, готовий приймач для чистого
  PHP, PSR-7, Laravel, Symfony і WordPress.
- Звірено з контрактом OpenAPI сервісу тестами.

## Встановлення

```sh
composer require prro-cloud/prro-php
```

Потрібен PHP 8.2 або новіший.

## Швидкий старт

Токен випускають у [кабінеті](https://app.prro.cloud/) у розділі токенів. На
касі в режимі `test` чеки безкоштовні й не потребують реєстрації в податковій.

```php
use PrroCloud\Prro\Client;
use PrroCloud\Prro\Enum\{PaymentType, ReceiptType};
use PrroCloud\Prro\Input\{Payment, ReceiptItem, SettlementReceipt};

$client = new Client(token: getenv('PRRO_TOKEN'));

$task = $client->receipts->create(new SettlementReceipt(
    cashRegisterId: $registerId, // з $client->cashRegisters->list()
    type: ReceiptType::Sale,
    items: [new ReceiptItem(name: 'Кава американо', quantity: '1', price: '65.00', taxLetters: 'А')],
    payments: [new Payment(type: PaymentType::Card, sum: '65.00')],
), idempotencyKey: "order-{$order->id}");

if (!$task->isDone()) { // ДПС не відповіла за 30 секунд
    $task = $client->tasks->wait($task->id);
}

$r = $task->result->receipt;
echo $r->fiscalNumber; // фіскальний номер від ДПС
echo $r->receiptUrl;   // чек для покупця: https://r.prro.cloud/…
echo $r->taxUrl;       // той самий чек у кабінеті ДПС
```

Повний цикл — каса, зміна, чек — у [examples/quickstart.php](examples/quickstart.php).

## Ключові механіки

### Ідемпотентність

Кожна фіскальна операція (чек, зміна, офлайн-сесія) несе ключ
ідемпотентності: повтор із тим самим ключем повертає початкове завдання, а не
реєструє другий документ.

У PHP це особливо важливо: процес живе рівно один запит, тож «повтор після
перезапуску» трапляється постійно — покупець оновив сторінку, FPM-воркер
перезапустився, черга повторила завдання. Передавайте ключ, похідний від
замовлення:

```php
$client->receipts->create($receipt, idempotencyKey: "order-{$order->id}");
```

Без `idempotencyKey` клієнт генерує UUIDv7 сам — його вистачає лише для
повторів у межах одного виклику.

Ключ прив'язаний до виду операції на касі, а тіло запиту сервер не
порівнює. Той самий ключ для іншої операції (чек → відкриття зміни) або іншої
каси — `IdempotencyKeyReusedException`. Але той самий ключ для **іншого чека**
на тій самій касі поверне перше завдання без помилки й без нового документа.
Тож ключ має бути унікальним для кожного документа: `"order-{$id}"` для
продажу, `"refund-{$id}"` для повернення.

### Синхронний і асинхронний режим

Типово виклик чекає на відповідь ДПС до 30 секунд. Не дочекався — повертає
незавершене завдання (`$task->isDone() === false`), яке дочікуються через
`$client->tasks->wait()`. З `async: true` завдання повертається одразу, а
результат приходить опитуванням або [вебхуком](#вебхуки).

**Синхронний режим у веб-запиті.** Очікування ДПС до 30 секунд упирається в
`fastcgi_read_timeout` nginx і `request_terminate_timeout` PHP-FPM, а покупець
бачить «зависле» оформлення. На оформленні замовлення використовуйте
`async: true` і вебхук або чергу (Laravel Queue, WooCommerce Action
Scheduler); синхронний режим — для CLI і воркерів черги.

```php
$task = $client->receipts->create($receipt, idempotencyKey: "order-{$order->id}", async: true);
$order->saveMeta('prro_task_id', $task->id); // результат прийде вебхуком task.completed
```

### Гроші

Суми — десяткові рядки, ніколи не `float`. Два типи, щоб їх не сплутати:

| Тип | Одиниця | Де | Приклад |
|---|---|---|---|
| `Money\Amount` | гривні, до 2 знаків | чеки, готівка, підсумки зміни | `"259.90"` |
| `Money\Kopecks` | копійки, до 4 знаків | білінг: баланс, ціна чека, рахунки | `"50.0000"` |

Параметри приймають і рядок (`price: '65.00'`), і `Amount`. Обчислені суми
рахуйте в копійках цілими числами і перетворюйте через
`Amount::fromKop(6500)`, назад — `$amount->kop()`. Сума з трьома знаками після
коми — `InvalidArgumentException` ще до запиту.

Не передавайте `float`. У файлі без `declare(strict_types=1)` PHP мовчки
перетворить його на рядок із 14 значущими цифрами: `65.5` стане `"65.5"`,
`0.1 + 0.2` — `"0.3"` (округлення сховане), а `1 / 3` — `"0.33333333333333"`,
і таку суму бібліотека відхилить. Зі `strict_types` `float` — `TypeError`.

### Помилки

Відповідь API з помилкою — `ApiException` зі статусом, машинним кодом і
текстом. Типові випадки мають власні класи:

| Виняток | Статус | Що означає |
|---|---|---|
| `UnauthorizedException` | 401 | токен недійсний, протермінований чи відкликаний |
| `ForbiddenException` | 403 | обмежений токен звернувся до білінгу чи вебхуків |
| `NotFoundException` | 404 | ресурсу немає або токен не має до нього доступу |
| `PaymentRequiredException` | 402 | баланс вичерпано — нові чеки заблоковано |
| `IdempotencyKeyReusedException` | 409 | ключ уже використано для іншої операції |
| `WebhookLimitReachedException` | 409 | вебхуків уже стільки, скільки кас, плюс один |
| `ValidationException` | 422 | запит не пройшов валідацію, подробиці в `$e->details` |
| `TaskFailedException` | 422 | ДПС відхилила документ або немає відкритої зміни |

Поза відповідями API: `TransportException` — мережа, DNS, TLS, тайм-аут;
`WaitTimeoutException` — `tasks->wait()` не дочекався; `InvalidArgumentException`
— перевірка аргументів до запиту; `DecodeException` — успішну відповідь не
розібрано. Усі винятки реалізують `PrroCloud\Prro\Exception\PrroException`.

```php
use PrroCloud\Prro\Exception\{PaymentRequiredException, TaskFailedException, TransportException};

try {
    $task = $client->receipts->create($receipt, idempotencyKey: "order-{$order->id}");
} catch (PaymentRequiredException) {
    // поповнити баланс
} catch (TaskFailedException $e) {
    error_log("{$e->fault?->code} {$e->fault?->class->value}"); // напр. dps.check_local_number_invalid resync
} catch (TransportException) {
    // відповіді немає: повторіть пізніше з тим самим ключем — другого чека не буде
}
```

`$e->fault->class` підказує, що робити далі: `Transient` — повторити,
`Request` — виправити запит, `UserAction` — потрібне рішення людини тощо.
Інші коди (наприклад, `delivery_in_flight`) приходять як `ApiException` із
заповненим `$e->errorCode`.

### Перелічення

Значення з відповідей — backed enum (`TaskStatus`, `RegisterState`, …). Якщо
сервер додасть нове значення раніше, ніж вийде нова версія бібліотеки, воно
стане case `Unknown`, а не зламає розбір; невідомі поля відповіді
ігноруються. Тип події вебхука — сирий рядок `$event->type`, типізований —
`$event->eventType()` (`null` для невідомої події).

### Чек для покупця

`receiptUrl` — копія чека, що відкривається без авторизації: надішліть її
листом, у месенджер або покажіть QR-кодом. Параметр `?format=png` дає чек
завширшки 576 px для термопринтера 80 мм, `?format=qr` — QR-код посилання.
`taxUrl` — той самий документ у кабінеті ДПС, незалежне підтвердження
фіскалізації.

## Вебхуки

Вебхук — адреса на вашому сервері, куди PRRO.cloud сам надсилає події:
чек зареєстровано, зміну закрито, каса перейшла в офлайн, баланс добігає
кінця. Опитувати завдання тоді не потрібно.

### 1. Зареєструйте вебхук і збережіть секрет

**Секрет** — рядок, яким сервіс підписує кожну доставку: HMAC-SHA256 тіла
запиту в заголовку `X-Signature`. Ваш приймач перевіряє підпис тим самим
секретом і так відрізняє справжні доставки від підроблених. Секрет
видається **рівно один раз** — під час створення вебхука; отримати його
повторно неможливо. Збережіть його одразу (змінна оточення, сховище
секретів); якщо втратили — створіть вебхук заново.

Створити вебхук можна в кабінеті (розділ «Webhooks» → «Додати webhook») або з
коду:

```php
use PrroCloud\Prro\Enum\WebhookEvent;

$wh = $client->webhooks->create(
    'https://shop.example.com/prro-hook',
    [WebhookEvent::TaskCompleted, WebhookEvent::TaskFailed], // [] — усі події
);
// $wh->secret — збережіть, наприклад, у PRRO_WEBHOOK_SECRET.
```

Вебхуків може бути не більше, ніж кас у клієнта, плюс один; призупинені теж
рахуються. Скільки зайнято і скільки дозволено, показує
`$client->webhooks->list()` — поля `used` і `limit`; понад ліміт `create()`
кидає `WebhookLimitReachedException`.

Подія `receipt.registered` несе повний вміст кожного зареєстрованого чека —
позиції, оплати, податки, посилання для покупця — і приходить також на чеки,
подані з панелі кабінету (`source === ReceiptSource::Cabinet`). Її дані
повертає `$e->receipt()`.

### 2. Приймайте доставки

> **Підпис перевіряється на сирих байтах тіла.** Передавайте тіло так, як
> його отримав сервер, — не перекодований JSON і не `$_POST`.

**Чистий PHP:**

```php
use PrroCloud\Prro\Enum\WebhookEvent;
use PrroCloud\Prro\Webhook\{Event, Webhook};

Webhook::handle(getenv('PRRO_WEBHOOK_SECRET'), function (Event $e): void {
    if ($e->eventType() === WebhookEvent::TaskCompleted) {
        $d = $e->task();
        markOrder($d->taskId, $d->status);
    }
});
```

`handle()` перевіряє підпис до будь-якої обробки тіла і сам надсилає
відповідь: 200 — оброблено, 401 — підпис недійсний, 405 — не POST, 413 — тіло
понад 10 МіБ, 400 — конверт не розібрано, 500 — ваш обробник кинув виняток
(сервіс повторить доставку).

**Laravel** — маршрут поза CSRF (`routes/api.php` або виняток у
`VerifyCsrfToken`):

```php
Route::post('/prro-hook', function (Request $request) {
    $r = Webhook::process(
        config('services.prro.webhook_secret'),
        fn (Event $e) => ProcessPrroEvent::dispatch($e->deliveryId, $e->type, $e->data),
        $request->method(),
        $request->getContent(),            // сирі байти
        (string) $request->header('X-Signature'),
    );
    return response($r->body, $r->status, $r->headers);
});
```

**Symfony** — так само через `Webhook::process()` з `$request->getContent()`
і `$request->headers->get('X-Signature')`; для PSR-7 запитів (Slim, Mezzio) —
`Webhook::fromPsrRequest($request, $secret)`.

**WordPress REST API:**

```php
add_action('rest_api_init', function () {
    register_rest_route('shop/v1', '/prro-hook', [
        'methods' => 'POST',
        'permission_callback' => '__return_true', // доступ перевіряє підпис
        'callback' => function (WP_REST_Request $request) {
            $r = Webhook::process(PRRO_WEBHOOK_SECRET, 'shop_handle_prro_event', 'POST',
                $request->get_body(), (string) $request->get_header('x-signature'));
            return new WP_REST_Response($r->body, $r->status, $r->headers);
        },
    ]);
});
```

Доставка гарантована «щонайменше один раз»: відкидайте повтори за
`$e->deliveryId` (унікальний ключ у тій самій транзакції, що й обробка).
Подію, яку приймач не прийняв, сервіс повторить пізніше, і вона може прийти
після наступних подій тієї самої каси — упорядковуйте їх за `$e->sequence`.
Перевірити приймач можна кнопкою «Тестова відправка» в кабінеті — прийде
подія `webhook.ping`. Приклад із дедуплікацією —
[examples/webhook.php](examples/webhook.php).

## Налаштування клієнта

```php
$client = new Client(
    token: getenv('PRRO_TOKEN'),
    baseUrl: getenv('PRRO_BASE_URL') ?: null, // null — https://api.prro.cloud
    timeout: 60.0,            // має перевищувати 30 с очікування ДПС
    connectTimeout: 10.0,
    maxRetries: 2,            // 0 вимикає повтори
    language: 'en',           // мова повідомлень про помилки; типово uk
    userAgent: 'my-shop/1.2', // на початку User-Agent
    logger: $psrLogger,       // журнал запитів на рівні debug, без токена й тіл
    transport: null,          // власний транспорт; типово CurlTransport
);
```

Повторюються лише запити, які безпечно повторити: `GET`, `PUT` і фіскальні
операції з ключем ідемпотентності.

**Транспорт.** Типовий `Http\CurlTransport` тримає з'єднання між викликами в
межах процесу. На хостингах із застарілим набором сертифікатів передайте
власний: `new CurlTransport(caBundle: '/path/to/cacert.pem')`; є також `proxy`
і `curlOptions`. Guzzle, Symfony HttpClient та інші PSR-18 клієнти
під'єднуються через `Http\Psr18Transport`:

```php
$factory = new \GuzzleHttp\Psr7\HttpFactory();
$client = new Client(
    token: $token,
    transport: new Psr18Transport(new \GuzzleHttp\Client(['timeout' => 60]), $factory, $factory),
);
```

**Swoole, RoadRunner, Octane.** Клієнт незмінний після створення, але curl-хендл
`CurlTransport` не можна ділити між корутинами: створюйте клієнт на корутину
або передайте PSR-18 клієнт із пулом з'єднань.

## Методи

| Сервіс | Методи |
|---|---|
| `$client->receipts` | `create` |
| `$client->tasks` | `get`, `wait` |
| `$client->cashRegisters` | `list`, `get`, `clearAttention`, `setCashBalance` |
| `$client->shifts` | `open`, `close` |
| `$client->offlineSessions` | `close`, `retry`, `abandon` |
| `$client->billing` | `usage` |
| `$client->invoices` | `list`, `all` (генератор по всіх сторінках) |
| `$client->webhooks` | `list`, `create`, `update`, `delete`, `deliveries`, `replay` |
| `$client->system` | `version`, `whoAmI`, `dps` |

Опис кожного методу — у phpDoc. Ще приклади:
[асинхронний режим](examples/async.php), [повернення](examples/refund.php),
[димова перевірка](examples/smoke.php) усіх маршрутів читання.

## Тестування власної інтеграції

`Testing\MockTransport` віддає задані відповіді й запам'ятовує запити, а
`Webhook::sign()` підписує тіло так, як це робить сервіс:

```php
use PrroCloud\Prro\Testing\MockTransport;

$mock = new MockTransport(
    MockTransport::json(201, ['task_id' => 't1', 'type' => 'receipt', 'status' => 'succeeded']),
);
$client = new Client(token: 'test', transport: $mock);
// … код інтеграції …
$this->assertSame('order-42', $mock->lastRequest()->header('Idempotency-Key'));

$body = json_encode(['delivery_id' => 'd1', 'event' => 'webhook.ping', 'data' => []]);
$result = Webhook::process($secret, $handler, 'POST', $body, Webhook::sign($secret, $body));
```

## Вбудовування у WordPress-плагін

Усі плагіни WordPress ділять один автозавантажувач, тож дві різні версії
бібліотеки в різних плагінах конфліктують. Префіксуйте простір імен через
[Strauss](https://github.com/BrianHenryIE/strauss) або
[PHP-Scoper](https://github.com/humbug/php-scoper). Бібліотека для цього не
будує імен класів із рядків і не оголошує глобальних функцій і констант.

## Документація API

- [prro.cloud/docs/api](https://prro.cloud/docs/api/) — документація REST API.
- [llms-full.txt](https://prro.cloud/llms-full.txt) — уся документація одним
  файлом для ШІ-асистента.
- [openapi.yaml](https://prro.cloud/api/openapi.yaml) — контракт OpenAPI 3.1.

Бібліотека відповідає контракту версії `Client::API_VERSION`. Клієнт для Go —
[prro-go](https://github.com/prro-cloud/prro-go).

## Розробка

```sh
make test        # PHPUnit: unit і контракт
make lint        # PHPStan (рівень max) і PHP-CS-Fixer
make fmt         # виправити стиль
make sync-spec   # оновити tests/testdata/openapi.yaml з репозиторію сервісу
```

Після `make sync-spec` тести контракту показують, яких методів, полів чи
значень перелічень бракує клієнту. Зміни — у [CHANGELOG.md](CHANGELOG.md),
вразливості — за [SECURITY.md](SECURITY.md).

## Ліцензія

[Apache 2.0](LICENSE)
