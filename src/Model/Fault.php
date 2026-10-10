<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Model;

use PrroCloud\Prro\Enum\FaultClass;
use PrroCloud\Prro\Internal\Attribute\Json;

/**
 * Структурований опис збою завдання. Простори імен коду: dps.* — відмова
 * фіскального сервера, fiscal.* — валідація документа, return.* — звірка
 * повернення з початковим чеком, signer.* — рівень КЕП, state.* — стан
 * реєстратора.
 */
final readonly class Fault
{
    /**
     * @param string               $code            стабільний ідентифікатор, напр. "dps.check_local_number_invalid"
     * @param FaultClass           $class           що може зарадити
     * @param array<string, mixed> $params          значення для підстановки в message
     * @param string               $message         текст для людини мовою з параметра language клієнта
     * @param string               $upstreamMessage дослівне повідомлення фіскального сервера
     * @param list<string>         $remediation     автоматичні виправлення, застосовані під час обробки
     *                                              завдання: "local_number_synced", "shift_adopted", …
     */
    public function __construct(
        #[Json('code')] public string $code = '',
        #[Json('class')] public FaultClass $class = FaultClass::Unknown,
        #[Json('params')] public array $params = [],
        #[Json('message')] public string $message = '',
        #[Json('upstream_message')] public string $upstreamMessage = '',
        #[Json('remediation')] public array $remediation = [],
    ) {}
}
