<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Webhook;

/**
 * Підпис відсутній, має не той формат або не збігається з тілом: доставку
 * надіслав не PRRO.cloud або тіло змінено.
 */
final class InvalidSignatureException extends WebhookException {}
