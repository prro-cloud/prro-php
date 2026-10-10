<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Service;

use PrroCloud\Prro\Internal\Hydrator;
use PrroCloud\Prro\Internal\Requester;
use PrroCloud\Prro\Model\CashBalanceDeclaration;
use PrroCloud\Prro\Model\CashRegister;
use PrroCloud\Prro\Model\CashRegisterState;
use PrroCloud\Prro\Money\Amount;

/**
 * Каси, доступні токену, і їхній стан.
 */
final readonly class CashRegistersService
{
    /** @internal */
    public function __construct(private Requester $requester) {}

    /**
     * Повертає каси, доступні токену, від найстаріших. Звідси беруть
     * ідентифікатор каси для решти методів — збережіть його в налаштуваннях
     * інтеграції.
     *
     * @return list<CashRegister>
     */
    public function list(): array
    {
        $data = $this->requester->send('GET', '/v1/cash-registers');

        return Hydrator::hydrateList(CashRegister::class, $data ?? [], 'cash_registers');
    }

    /**
     * Повертає поточний стан каси id.
     */
    public function get(string $id): CashRegisterState
    {
        $data = $this->requester->send('GET', Requester::path('/v1/cash-registers/%s', $id));

        return Hydrator::hydrate(CashRegisterState::class, $data ?? []);
    }

    /**
     * Знімає з каси позначку «потребує уваги». false — позначки й не було.
     * Зупинене надсилання офлайн-пакетів додатково відновлюють через
     * {@see OfflineSessionsService::retry()}.
     */
    public function clearAttention(string $id): bool
    {
        $data = $this->requester->send('DELETE', Requester::path('/v1/cash-registers/%s/attention', $id));

        return Hydrator::bool($data ?? [], 'cleared');
    }

    /**
     * Заявляє фактичний залишок готівки: переставляє лічильник на
     * перераховану суму — коли касу підключили з непорожнім ящиком або
     * лічильник розійшовся з фактом. Операція не фіскальна; рух готівки
     * реєструють {@see \PrroCloud\Prro\Input\ServiceReceipt}. Від'ємна сума —
     * {@see \PrroCloud\Prro\Exception\ValidationException}.
     */
    public function setCashBalance(string $id, Amount|string $amount): CashBalanceDeclaration
    {
        $path = Requester::path('/v1/cash-registers/%s/cash-balance', $id);
        $data = $this->requester->send('PUT', $path, body: ['amount' => Amount::of($amount)]);

        return Hydrator::hydrate(CashBalanceDeclaration::class, $data ?? []);
    }
}
