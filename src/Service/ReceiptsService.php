<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Service;

use PrroCloud\Prro\Exception\ApiException;
use PrroCloud\Prro\Exception\TransportException;
use PrroCloud\Prro\Input\ServiceReceipt;
use PrroCloud\Prro\Input\SettlementReceipt;
use PrroCloud\Prro\Input\StornoReceipt;
use PrroCloud\Prro\Internal\Hydrator;
use PrroCloud\Prro\Internal\Requester;
use PrroCloud\Prro\Model\Task;

/**
 * Реєстрація розрахункових документів.
 */
final readonly class ReceiptsService
{
    /** @internal */
    public function __construct(private Requester $requester) {}

    /**
     * Реєструє розрахунковий документ: сервіс видає локальний номер,
     * підписує документ КЕП і надсилає до ДПС.
     *
     * Синхронно (типово) виклик чекає на результат до 30 секунд: успіх — це
     * завдання зі status === TaskStatus::Succeeded, а фіскальний номер і
     * посилання для покупця — у result->receipt. Якщо ДПС не встигла,
     * повертається незавершене завдання — дочекайтеся його через
     * {@see TasksService::wait()}. З async: true незавершене завдання
     * повертається одразу.
     *
     * Передавайте idempotencyKey, похідний від замовлення ("order-10412"):
     * повтор із тим самим ключем поверне те саме завдання, а не другий чек.
     * Без ключа клієнт генерує UUIDv7 — його вистачає для повторів у межах
     * одного виклику.
     *
     * @throws \PrroCloud\Prro\Exception\TaskFailedException             ДПС відхилила документ, немає відкритої зміни,
     *                                                                   сторно не останнього документа тощо
     * @throws \PrroCloud\Prro\Exception\PaymentRequiredException        баланс вичерпано
     * @throws \PrroCloud\Prro\Exception\IdempotencyKeyReusedException ключ уже використано для іншої операції
     * @throws ApiException
     * @throws TransportException
     */
    public function create(SettlementReceipt|StornoReceipt|ServiceReceipt $receipt, ?string $idempotencyKey = null, bool $async = false): Task
    {
        $data = $this->requester->send('POST', '/v1/receipts', body: $receipt, fiscal: true, idempotencyKey: $idempotencyKey, async: $async);

        return Hydrator::hydrate(Task::class, $data ?? []);
    }
}
