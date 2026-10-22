# Зміни

Формат — [Keep a Changelog](https://keepachangelog.com/uk/1.1.0/), версії — за
[семантичним версіонуванням](https://semver.org/lang/uk/). До 1.0.0 мінорна
версія може містити несумісні зміни API.

## [Unreleased]

Контракт API 0.8.0, паритет із prro-go 0.3.0.

### Змінено

- **Несумісно:** сторно — окрема форма `Input\StornoReceipt` без позицій,
  оплат і суми: лише каса, фіскальний номер скасовуваного чека, касир і
  примітка. `SettlementReceipt` тепер приймає тільки `Sale` і `Return`.
  Сторно в старій формі ДПС відхиляла завжди (помилка 9), тож успішних
  сторно цей перехід не зачіпає. Але `new SettlementReceipt(type:
  ReceiptType::Storno, ...)` тепер кидає `InvalidArgumentException` уже в
  конструкторі, а не `TaskFailedException` з `receipts->create()`. Сторнувати можна лише останній документ каси; інакше — збій
  завдання з кодом `storno.*`.
- `receipts->create()` приймає `StornoReceipt`.
- **Несумісно:** податки в даних `receipt.registered` (`ReceiptData::$taxes`)
  тепер `Model\TaxTotal` — той самий клас, що в підсумках Z-звіту, як і в
  контракті. `Webhook\Data\ReceiptTax` вилучено; поля ті самі.

### Додано

- `DocResult::$totals` — підсумки Z-звіту (`Model\ZReportTotals`): продажі й
  повернення з розкладом за формами оплати та податками
  (`Model\ZReportTotalsSide`, `Model\TaxTotal`), службові внесення й видачі.
  Є лише в Z-звіту — `$task->result?->zReport?->totals`.
- `Webhook\Data\ShiftData::$zReport` — у події `shift.closed` Z-звіт, яким
  закрито зміну, разом із підсумками.
- Тест контракту розіменовує `$ref` і зливає `allOf` у схемах і звіряє
  `StornoReceiptInput`, `ZReportTotals`, `TaxTotal`, `ShiftEventData`.

## [0.2.0] — 2026-10-10

Контракт API 0.6.0, паритет із prro-go 0.2.0.

### Змінено

- **Несумісно:** `webhooks->list()` повертає `Model\WebhookList` — перелік
  вебхуків разом із лімітом: `limit` (кас у клієнта плюс один) і `used`
  (зареєстровано, включно з призупиненими). Було: `list<WebhookEndpoint>` —
  тепер `$list->webhooks`.
- `Enum\PaymentType` отримав case `Unknown`, бо форма оплати тепер приходить і
  у відповідях; `Input\Payment` його відхиляє.

### Додано

- Подія `receipt.registered` (`WebhookEvent::ReceiptRegistered`) — повний
  вміст кожного зареєстрованого чека, і з API, і з панелі; дані —
  `Webhook\Data\ReceiptData` через `$e->receipt()`, джерело —
  `Enum\ReceiptSource`.
- `WebhookLimitReachedException` — 409 `webhook_limit_reached` у
  `webhooks->create()`, коли ліміт вебхуків вичерпано; limit і used — у
  `details`.
- Тест контракту звіряє обгортку відповіді `GET /v1/webhooks` і дані
  `receipt.registered` (`ReceiptRegisteredData`).

### Документація

- Строки зберігання історії доставок: доставлені — 30 днів, невдалі — 14 днів
  від останньої спроби; `webhooks->replay()` доступний, поки доставка є в
  історії.

## [0.1.0] — 2026-10-10

### Додано

- Клієнт машинного API PRRO.cloud (контракт 0.5.0): чеки, завдання, каси,
  зміни, офлайн-сесії, білінг, рахунки, вебхуки, службові маршрути — паритет
  із prro-go 0.1.0.
- Без runtime-залежностей: лише `ext-curl` і `ext-json`; PHP 8.2+.
- Автоматичний ключ ідемпотентності (UUIDv7), один на всі повтори виклику, і
  безпечні повтори з експоненційною затримкою та `Retry-After`.
- Ієрархія винятків `PrroException` з окремими класами за кодом і статусом
  відповіді та описом збою завдання `Fault`.
- `tasks->wait()` — очікування завершення завдання з тайм-аутом.
- `invoices->all()` — обхід усіх рахунків генератором.
- Моделі відповідей — `readonly`-класи; невідомі поля ігноруються, невідомі
  значення перелічень стають case `Unknown`.
- `Money\Amount` і `Money\Kopecks` — суми десятковими рядками з перевіркою
  формату до запиту.
- Транспорти `CurlTransport` (типовий), `Psr18Transport` і
  `Testing\MockTransport` для тестів інтеграцій.
- Вебхуки: перевірка підпису, типізовані дані подій, `Webhook::handle()` для
  чистого PHP, `Webhook::process()` для фреймворків і `fromPsrRequest()`.
- Тести відповідності контракту OpenAPI.

[Unreleased]: https://github.com/prro-cloud/prro-php/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/prro-cloud/prro-php/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/prro-cloud/prro-php/releases/tag/v0.1.0
