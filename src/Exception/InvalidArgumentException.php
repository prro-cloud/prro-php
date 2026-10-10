<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Exception;

/**
 * Аргумент не пройшов перевірку на боці клієнта — ще до запиту: порожній
 * ідентифікатор, сума з трьома знаками після коми, чек не того виду тощо.
 */
final class InvalidArgumentException extends \InvalidArgumentException implements PrroException {}
