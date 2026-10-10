<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Exception;

/**
 * 409 (код idempotency_key_reused): цей Idempotency-Key уже використано для
 * іншої операції.
 */
final class IdempotencyKeyReusedException extends ApiException {}
