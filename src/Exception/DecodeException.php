<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Exception;

/**
 * Успішну відповідь API не вдалося розібрати: тіло не є JSON або поле має
 * не той тип. Невідомі поля й нові значення перелічень цієї помилки не
 * спричиняють.
 */
final class DecodeException extends \RuntimeException implements PrroException {}
