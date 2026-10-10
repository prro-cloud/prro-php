<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Webhook;

use PrroCloud\Prro\Exception\PrroException;

/**
 * Доставку вебхука не прийнято: конверт або дані події не розібрано. Окремі
 * випадки — {@see InvalidSignatureException} і {@see PayloadTooLargeException}.
 */
class WebhookException extends \RuntimeException implements PrroException {}
