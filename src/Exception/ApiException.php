<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Exception;

use PrroCloud\Prro\Internal\Hydrator;
use PrroCloud\Prro\Model\Fault;

/**
 * Відповідь API з помилкою: конверт {code, message, details}.
 *
 * Типові випадки мають власні підкласи ({@see NotFoundException},
 * {@see TaskFailedException}, …): клас обирається за машинним кодом
 * відповіді, а якщо коду немає — за HTTP-статусом. Решта кодів
 * (наприклад, "delivery_in_flight") приходять як ApiException із
 * заповненим {@see $errorCode}.
 */
class ApiException extends \RuntimeException implements PrroException
{
    /** Межа повідомлення з тіла, яке не є конвертом API (сторінка помилки проксі). */
    private const MAX_MESSAGE_BYTES = 512;

    /** Скільки байтів тіла помилки розбирати. */
    private const MAX_BODY_BYTES = 64 << 10;

    /** @var array<string, class-string<self>> */
    private const BY_CODE = [
        'unauthorized' => UnauthorizedException::class,
        'forbidden' => ForbiddenException::class,
        'not_found' => NotFoundException::class,
        'payment_required' => PaymentRequiredException::class,
        'idempotency_key_reused' => IdempotencyKeyReusedException::class,
        'webhook_limit_reached' => WebhookLimitReachedException::class,
        'validation_failed' => ValidationException::class,
        'task_failed' => TaskFailedException::class,
    ];

    /** @var array<int, class-string<self>> */
    private const BY_STATUS = [
        401 => UnauthorizedException::class,
        403 => ForbiddenException::class,
        404 => NotFoundException::class,
        402 => PaymentRequiredException::class,
    ];

    /** @var array<int, string> */
    private const STATUS_TEXT = [
        400 => 'bad request',
        401 => 'unauthorized',
        402 => 'payment required',
        403 => 'forbidden',
        404 => 'not found',
        405 => 'method not allowed',
        408 => 'request timeout',
        409 => 'conflict',
        413 => 'request entity too large',
        422 => 'unprocessable entity',
        429 => 'too many requests',
        500 => 'internal server error',
        502 => 'bad gateway',
        503 => 'service unavailable',
        504 => 'gateway timeout',
    ];

    /**
     * @param int                       $statusCode   HTTP-статус відповіді
     * @param string                    $errorCode    стабільний машинний код помилки ("validation_failed",
     *                                                "task_failed", …); саме на нього реагує інтеграція.
     *                                                Порожній, якщо відповідь прийшла не від API
     * @param string                    $errorMessage пояснення для людини; покладатися на його текст
     *                                                програмно не можна
     * @param array<mixed>|null         $details      подробиці помилки як є, наприклад перелік полів, які не
     *                                                пройшли валідацію
     * @param string                    $taskId       завдання, що завершилося помилкою; лише для task_failed
     * @param Fault|null                $fault        структурований опис збою завдання; лише для task_failed
     */
    public function __construct(
        public readonly int $statusCode,
        public readonly string $errorCode = '',
        public readonly string $errorMessage = '',
        public readonly ?array $details = null,
        public readonly string $taskId = '',
        public readonly ?Fault $fault = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(self::describe($statusCode, $errorCode, $errorMessage, $fault), $statusCode, $previous);
    }

    /**
     * Створює виняток потрібного підкласу: за кодом, а якщо код невідомий —
     * за статусом.
     *
     * @param array<mixed>|null $details
     */
    public static function create(
        int $statusCode,
        string $errorCode = '',
        string $errorMessage = '',
        ?array $details = null,
        string $taskId = '',
        ?Fault $fault = null,
    ): self {
        $class = self::BY_CODE[$errorCode] ?? self::BY_STATUS[$statusCode] ?? self::class;

        return new $class($statusCode, $errorCode, $errorMessage, $details, $taskId, $fault);
    }

    /**
     * Будує виняток із відповіді не-2xx. Тіло, яке не є конвертом API,
     * потрапляє в повідомлення (обрізане), а код лишається порожнім.
     *
     * @internal
     */
    public static function fromResponse(int $statusCode, string $body): self
    {
        $raw = substr($body, 0, self::MAX_BODY_BYTES);

        try {
            $env = json_decode($raw, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\JsonException) {
            $env = null;
        }
        if (is_array($env) && is_string($env['code'] ?? null) && $env['code'] !== '') {
            $code = $env['code'];
            $message = is_string($env['message'] ?? null) ? $env['message'] : '';
            $details = is_array($env['details'] ?? null) ? $env['details'] : null;
            $taskId = '';
            $fault = null;
            if ($code === 'task_failed' && $details !== null) {
                $taskId = is_string($details['task_id'] ?? null) ? $details['task_id'] : '';
                if (is_array($details['fault'] ?? null)) {
                    try {
                        $fault = Hydrator::hydrate(Fault::class, $details['fault']);
                    } catch (DecodeException) {
                        // Опис збою — довідковий: без нього помилка лишається повною.
                    }
                }
            }

            return self::create($statusCode, $code, $message, $details, $taskId, $fault);
        }

        $message = trim($raw);
        if (strlen($message) > self::MAX_MESSAGE_BYTES) {
            $message = mb_strcut($message, 0, self::MAX_MESSAGE_BYTES, 'UTF-8') . '…';
        }
        if ($message === '') {
            $message = self::STATUS_TEXT[$statusCode] ?? '';
        }

        return self::create($statusCode, '', $message);
    }

    private static function describe(int $statusCode, string $errorCode, string $errorMessage, ?Fault $fault): string
    {
        $s = 'prro: ' . $statusCode;
        if ($errorCode !== '') {
            $s .= ' ' . $errorCode;
        }
        if ($fault !== null && $fault->code !== '') {
            $s .= ' (' . $fault->code . ')';
        }
        if ($errorMessage !== '') {
            $s .= ': ' . $errorMessage;
        }

        return $s;
    }
}
