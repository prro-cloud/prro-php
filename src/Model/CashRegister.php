<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Model;

use PrroCloud\Prro\Enum\RegisterMode;
use PrroCloud\Prro\Enum\RegisterState;
use PrroCloud\Prro\Internal\Attribute\Json;

/**
 * Каса в переліку: ідентифікатор для звернень і реквізити, за якими її
 * впізнає людина.
 */
final readonly class CashRegister
{
    /**
     * @param string $id           ідентифікатор каси в API: cashRegisterId чека і параметр решти методів.
     *                             Фіскальний номер для цього не годиться
     * @param string $fiscalNumber фіскальний номер ПРРО, присвоєний ДПС
     * @param string $localNumber  власний номер каси у клієнта
     * @param string $orgName      назва суб'єкта господарювання
     * @param string $pointName    назва точки продажу
     * @param string $pointAddress адреса точки продажу
     */
    public function __construct(
        #[Json('id')] public string $id = '',
        #[Json('fiscal_number')] public string $fiscalNumber = '',
        #[Json('local_number')] public string $localNumber = '',
        #[Json('org_name')] public string $orgName = '',
        #[Json('point_name')] public string $pointName = '',
        #[Json('point_address')] public string $pointAddress = '',
        #[Json('mode')] public RegisterMode $mode = RegisterMode::Unknown,
        #[Json('state')] public RegisterState $state = RegisterState::Unknown,
    ) {}
}
