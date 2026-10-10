<?php

declare(strict_types=1);

namespace PrroCloud\Prro;

use PrroCloud\Prro\Http\CurlTransport;
use PrroCloud\Prro\Http\Transport;
use PrroCloud\Prro\Internal\Clock;
use PrroCloud\Prro\Internal\Requester;
use PrroCloud\Prro\Internal\SystemClock;
use PrroCloud\Prro\Service\BillingService;
use PrroCloud\Prro\Service\CashRegistersService;
use PrroCloud\Prro\Service\InvoicesService;
use PrroCloud\Prro\Service\OfflineSessionsService;
use PrroCloud\Prro\Service\ReceiptsService;
use PrroCloud\Prro\Service\ShiftsService;
use PrroCloud\Prro\Service\SystemService;
use PrroCloud\Prro\Service\TasksService;
use PrroCloud\Prro\Service\WebhooksService;
use Psr\Log\LoggerInterface;

/**
 * Клієнт машинного API PRRO.cloud.
 *
 *     $client = new Client(token: getenv('PRRO_TOKEN'));
 *     $task = $client->receipts->create($receipt, idempotencyKey: 'order-10412');
 *
 * Після створення клієнт незмінний. Без параметрів він звертається до
 * {@see Client::DEFAULT_BASE_URL}, чекає на відповідь до 60 секунд і
 * повторює безпечні запити двічі.
 */
final class Client
{
    /** Версія бібліотеки; потрапляє в заголовок User-Agent. */
    public const VERSION = '0.2.0';

    /** Версія контракту API (info.version в openapi.yaml), з якою зібрано бібліотеку. */
    public const API_VERSION = '0.6.0';

    /** Базова адреса машинного API PRRO.cloud. */
    public const DEFAULT_BASE_URL = 'https://api.prro.cloud';

    /** Реєстрація чеків. */
    public readonly ReceiptsService $receipts;

    /** Стан і результат фіскальних завдань. */
    public readonly TasksService $tasks;

    /** Каси, доступні токену, і їхній стан. */
    public readonly CashRegistersService $cashRegisters;

    /** Відкриття і закриття змін. */
    public readonly ShiftsService $shifts;

    /** Керування офлайн-сесією каси. */
    public readonly OfflineSessionsService $offlineSessions;

    /** Баланс і тарифікація. */
    public readonly BillingService $billing;

    /** Рахунки клієнта. */
    public readonly InvoicesService $invoices;

    /** Вебхуки та історія доставок. */
    public readonly WebhooksService $webhooks;

    /** Службові маршрути: версія сервісу, дозволи токена, режим роботи з ДПС. */
    public readonly SystemService $system;

    /**
     * @param string               $token          машинний токен із кабінету; порожній — без заголовка Authorization
     * @param string|null          $baseUrl        інша адреса API, напр. локальний сервер; null або '' —
     *                                             DEFAULT_BASE_URL, тож значення можна брати прямо зі змінної
     *                                             оточення
     * @param float                $timeout        межа запиту, с; має перевищувати 30 с синхронного очікування ДПС.
     *                                             Не діє, якщо передано transport
     * @param float                $connectTimeout межа встановлення з'єднання, с. Не діє, якщо передано transport
     * @param int                  $maxRetries     скільки разів повторювати безпечний запит після мережевого збою
     *                                             або відповіді 429/5xx; 0 вимикає повтори
     * @param string|null          $language       мова повідомлень про помилки (Accept-Language): "uk" або "en";
     *                                             null — типова мова сервера (uk)
     * @param string|null          $userAgent      назва вашого застосунку на початку User-Agent:
     *                                             "my-shop/1.2 prro-php/0.1.0 (PHP 8.3.12; Linux)"
     * @param LoggerInterface|null $logger         журнал запитів на рівні debug: метод, шлях, спроба, статус і
     *                                             тривалість. Токен і тіла запитів у журнал не потрапляють
     * @param Transport|null       $transport      власний HTTP-транспорт; null — CurlTransport
     * @param float                $retryMin       @internal лише для тестів
     * @param float                $retryMax       @internal лише для тестів
     * @param float                $pollMin        @internal лише для тестів
     * @param float                $pollMax        @internal лише для тестів
     * @param Clock|null           $clock          @internal лише для тестів
     */
    public function __construct(
        string $token,
        ?string $baseUrl = null,
        float $timeout = 60.0,
        float $connectTimeout = 10.0,
        int $maxRetries = 2,
        ?string $language = null,
        ?string $userAgent = null,
        ?LoggerInterface $logger = null,
        ?Transport $transport = null,
        float $retryMin = 0.5,
        float $retryMax = 8.0,
        float $pollMin = 0.5,
        float $pollMax = 5.0,
        ?Clock $clock = null,
    ) {
        $baseUrl = rtrim($baseUrl ?? '', '/');
        $clock ??= new SystemClock();
        $requester = new Requester(
            token: $token,
            baseUrl: $baseUrl !== '' ? $baseUrl : self::DEFAULT_BASE_URL,
            userAgent: self::userAgent($userAgent),
            language: $language,
            maxRetries: max($maxRetries, 0),
            retryMin: $retryMin,
            retryMax: $retryMax,
            transport: $transport ?? new CurlTransport($timeout, $connectTimeout),
            clock: $clock,
            logger: $logger,
        );

        $this->receipts = new ReceiptsService($requester);
        $this->tasks = new TasksService($requester, $clock, $pollMin, $pollMax);
        $this->cashRegisters = new CashRegistersService($requester);
        $this->shifts = new ShiftsService($requester);
        $this->offlineSessions = new OfflineSessionsService($requester);
        $this->billing = new BillingService($requester);
        $this->invoices = new InvoicesService($requester);
        $this->webhooks = new WebhooksService($requester);
        $this->system = new SystemService($requester);
    }

    private static function userAgent(?string $app): string
    {
        $ua = 'prro-php/' . self::VERSION . ' (PHP ' . PHP_VERSION . '; ' . PHP_OS_FAMILY . ')';
        $app = trim($app ?? '');

        return $app !== '' ? $app . ' ' . $ua : $ua;
    }
}
