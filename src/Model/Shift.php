<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Model;

use PrroCloud\Prro\Enum\ShiftStatus;
use PrroCloud\Prro\Internal\Attribute\Json;

/**
 * Зміна каси.
 */
final readonly class Shift
{
    /**
     * @param int  $number    наскрізний номер зміни в межах каси («Зміна №147»)
     * @param bool $testing   зміну відкрито в режимі test, усі її документи тестові
     * @param int  $documents скільки документів створила зміна, усіх видів
     */
    public function __construct(
        #[Json('id')] public string $id = '',
        #[Json('number')] public int $number = 0,
        #[Json('status')] public ShiftStatus $status = ShiftStatus::Unknown,
        #[Json('testing')] public bool $testing = false,
        #[Json('opened_at')] public ?\DateTimeImmutable $openedAt = null,
        #[Json('documents')] public int $documents = 0,
    ) {}
}
