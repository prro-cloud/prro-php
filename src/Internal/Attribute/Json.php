<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Internal\Attribute;

/**
 * Ім'я поля JSON для параметра конструктора моделі.
 *
 * @internal
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final readonly class Json
{
    public function __construct(public string $name) {}
}
