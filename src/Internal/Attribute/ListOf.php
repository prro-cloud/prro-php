<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Internal\Attribute;

/**
 * Тип елементів масиву: модель, перелічення, Amount тощо.
 *
 * @internal
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final readonly class ListOf
{
    /**
     * @param class-string $class
     */
    public function __construct(public string $class) {}
}
