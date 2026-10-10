<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Model;

use PrroCloud\Prro\Internal\Attribute\Json;

/**
 * Клієнт, від імені якого діє токен, і межі його дозволів.
 */
final readonly class Identity
{
    /**
     * @param string                  $clientId         клієнт, від імені якого діє токен
     * @param list<string>            $scopes           дозволи, видані токену
     * @param bool                    $allCashRegisters токен діє на всі каси клієнта, поточні й майбутні;
     *                                                  false — лише на перелічені в cashRegisters
     * @param list<string>            $cashRegisters    ідентифікатори кас обмеженого токена; порожній, коли
     *                                                  allCashRegisters
     * @param string                  $tokenId          ідентифікатор токена (jti); за ним токен відкликають
     *                                                  у кабінеті
     * @param \DateTimeImmutable|null $expiresAt        коли токен спливає; null — токен безстроковий.
     *                                                  Приймається і рядок RFC 3339 (за контрактом), і
     *                                                  число секунд Unix — так його віддають сервери до
     *                                                  виправлення формату
     */
    public function __construct(
        #[Json('client_id')] public string $clientId = '',
        #[Json('scopes')] public array $scopes = [],
        #[Json('all_cash_registers')] public bool $allCashRegisters = false,
        #[Json('cash_registers')] public array $cashRegisters = [],
        #[Json('jti')] public string $tokenId = '',
        #[Json('expires_at')] public ?\DateTimeImmutable $expiresAt = null,
    ) {}
}
