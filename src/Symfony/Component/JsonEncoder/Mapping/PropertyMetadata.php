<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Mapping;

use Symfony\Component\TypeInfo\Type;

/**
 * Holds encoding/decoding metadata about a given property.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @experimental
 */
final class PropertyMetadata
{
    /**
     * @param list<string|\Closure> $toJsonValueTransformers
     * @param list<string|\Closure> $toNativeValueTransformers
     */
    public function __construct(
        private string $name,
        private Type $type,
        private array $toJsonValueTransformers = [],
        private array $toNativeValueTransformers = [],
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function withName(string $name): self
    {
        return new self($name, $this->type, $this->toJsonValueTransformers, $this->toNativeValueTransformers);
    }

    public function getType(): Type
    {
        return $this->type;
    }

    public function withType(Type $type): self
    {
        return new self($this->name, $type, $this->toJsonValueTransformers, $this->toNativeValueTransformers);
    }

    /**
     * @return list<string|\Closure>
     */
    public function getToJsonValueTransformer(): array
    {
        return $this->toJsonValueTransformers;
    }

    /**
     * @param list<string|\Closure> $toJsonValueTransformers
     */
    public function withToJsonValueTransformers(array $toJsonValueTransformers): self
    {
        return new self($this->name, $this->type, $toJsonValueTransformers, $this->toNativeValueTransformers);
    }

    public function withAdditionalToJsonValueTransformer(string|\Closure $toJsonValueTransformer): self
    {
        $toJsonValueTransformers = $this->toJsonValueTransformers;

        $toJsonValueTransformers[] = $toJsonValueTransformer;
        $toJsonValueTransformers = array_values(array_unique($toJsonValueTransformers));

        return $this->withToJsonValueTransformers($toJsonValueTransformers);
    }

    /**
     * @return list<string|\Closure>
     */
    public function getToNativeValueTransformers(): array
    {
        return $this->toNativeValueTransformers;
    }

    /**
     * @param list<string|\Closure> $toNativeValueTransformers
     */
    public function withToNativeValueTransformers(array $toNativeValueTransformers): self
    {
        return new self($this->name, $this->type, $this->toJsonValueTransformers, $toNativeValueTransformers);
    }

    public function withAdditionalToNativeValueTransformer(string|\Closure $toNativeValueTransformer): self
    {
        $toNativeValueTransformers = $this->toNativeValueTransformers;

        $toNativeValueTransformers[] = $toNativeValueTransformer;
        $toNativeValueTransformers = array_values(array_unique($toNativeValueTransformers));

        return $this->withToNativeValueTransformers($toNativeValueTransformers);
    }
}
