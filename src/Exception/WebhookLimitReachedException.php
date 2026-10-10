<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Exception;

/**
 * 409 (код webhook_limit_reached): ліміт вебхуків вичерпано — не більше, ніж
 * кас у клієнта, плюс один; призупинені теж рахуються. Поточні limit і used —
 * у {@see ApiException::$details}.
 */
final class WebhookLimitReachedException extends ApiException {}
