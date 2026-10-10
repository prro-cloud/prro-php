<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Exception;

/**
 * Запит не дійшов до API або відповіді не отримано: мережа, DNS, TLS,
 * тайм-аут. Клієнт уже повторив безпечний запит, скільки дозволяє
 * maxRetries. Для фіскальної операції повтор із тим самим ключем
 * ідемпотентності безпечний: другого документа не буде.
 */
final class TransportException extends \RuntimeException implements PrroException {}
