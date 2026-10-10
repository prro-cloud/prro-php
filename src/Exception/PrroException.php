<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Exception;

/**
 * Спільний інтерфейс усіх винятків бібліотеки: catch (PrroException $e)
 * перехоплює будь-яку її помилку.
 */
interface PrroException extends \Throwable {}
