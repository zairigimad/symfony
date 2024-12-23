<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Attribute;

use Symfony\Component\JsonEncoder\Exception\LogicException;

/**
 * Defines a callable or a {@see \Symfony\Component\JsonEncoder\ValueTransformer\ValueTransformerInterface} service id
 * that will be used to transform the property data during encoding and decoding.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @experimental
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class ValueTransformer
{
    private \Closure|string|null $toNativeValue;
    private \Closure|string|null $toJsonValue;

    /**
     * @param (callable(mixed, array<string, mixed>=): mixed)|string|null $toNativeValue
     * @param (callable(mixed, array<string, mixed>=): mixed)|string|null $toJsonValue
     */
    public function __construct(
        callable|string|null $toNativeValue = null,
        callable|string|null $toJsonValue = null,
    ) {
        if (!$toNativeValue && !$toJsonValue) {
            throw new LogicException('#[ValueTransformer] attribute must declare either $toNativeValue or $toJsonValue.');
        }

        if (\is_callable($toNativeValue)) {
            $toNativeValue = $toNativeValue(...);
        }

        if (\is_callable($toJsonValue)) {
            $toJsonValue = $toJsonValue(...);
        }

        $this->toNativeValue = $toNativeValue;
        $this->toJsonValue = $toJsonValue;
    }

    public function getToNativeValueTransformer(): string|\Closure|null
    {
        return $this->toNativeValue;
    }

    public function getToJsonValueTransformer(): string|\Closure|null
    {
        return $this->toJsonValue;
    }
}
