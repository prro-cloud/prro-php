<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Webhook;

/**
 * Тіло доставки перевищує {@see Webhook::MAX_BODY_BYTES}.
 */
final class PayloadTooLargeException extends WebhookException {}
