<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Webhook\Data;

use PrroCloud\Prro\Internal\Attribute\Json;
use PrroCloud\Prro\Model\DocResult;

/**
 * Дані подій shift.opened і shift.closed.
 */
final readonly class ShiftData
{
    /**
     * @param string         $taskId  завдання, яке відкрило чи закрило зміну. Зазвичай це відкриття чи
     *                                 закриття зміни, але зміну може закрити й чек (якщо попереднє закриття
     *                                 не завершилося) або автоматичне виправлення розбіжності з ДПС
     * @param bool           $testing зміна тестова: каса в режимі test
     * @param DocResult|null $zReport лише в shift.closed: Z-звіт, яким закрито зміну, з підсумками
     *                                (totals). Якщо зміну закрило завдання закриття, це той самий
     *                                об'єкт, що в його result->zReport. null, якщо в сервісі немає
     *                                Z-звіту цієї зміни
     */
    public function __construct(
        #[Json('cash_register_id')] public string $cashRegisterId = '',
        #[Json('shift_id')] public string $shiftId = '',
        #[Json('task_id')] public string $taskId = '',
        #[Json('testing')] public bool $testing = false,
        #[Json('zreport')] public ?DocResult $zReport = null,
    ) {}
}
