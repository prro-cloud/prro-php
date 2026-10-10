<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Webhook\Data;

use PrroCloud\Prro\Internal\Attribute\Json;

/**
 * Дані події key.expiring.
 */
final readonly class KeyData
{
    /**
     * @param list<string>       $cashRegisterIds каси, що підписують цим ключем
     * @param string             $certSubject     власник сертифіката, як його вказав АЦСК
     * @param \DateTimeImmutable $certValidTo     коли сертифікат спливає
     */
    public function __construct(
        #[Json('key_id')] public string $keyId = '',
        #[Json('cash_register_ids')] public array $cashRegisterIds = [],
        #[Json('cert_subject')] public string $certSubject = '',
        #[Json('cert_valid_to')] public \DateTimeImmutable $certValidTo = new \DateTimeImmutable('@0'),
    ) {}
}
