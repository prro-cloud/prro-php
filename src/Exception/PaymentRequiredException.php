<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Exception;

/**
 * 402 (код payment_required): баланс нижче порога, нові чеки заблоковано до
 * поповнення. Операції зі змінами лишаються доступними.
 */
final class PaymentRequiredException extends ApiException {}
