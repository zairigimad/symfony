<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Mapping\Decode;

use Psr\Container\ContainerInterface;
use Symfony\Component\JsonEncoder\Attribute\EncodedName;
use Symfony\Component\JsonEncoder\Attribute\ValueTransformer;
use Symfony\Component\JsonEncoder\Exception\InvalidArgumentException;
use Symfony\Component\JsonEncoder\Exception\RuntimeException;
use Symfony\Component\JsonEncoder\Mapping\PropertyMetadataLoaderInterface;
use Symfony\Component\JsonEncoder\ValueTransformer\ValueTransformerInterface;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolverInterface;

/**
 * Enhances properties decoding metadata based on properties' attributes.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class AttributePropertyMetadataLoader implements PropertyMetadataLoaderInterface
{
    public function __construct(
        private PropertyMetadataLoaderInterface $decorated,
        private ContainerInterface $valueTransformers,
        private TypeResolverInterface $typeResolver,
    ) {
    }

    public function load(string $className, array $options = [], array $context = []): array
    {
        $initialResult = $this->decorated->load($className, $options, $context);
        $result = [];

        foreach ($initialResult as $initialEncodedName => $initialMetadata) {
            try {
                $propertyReflection = new \ReflectionProperty($className, $initialMetadata->getName());
            } catch (\ReflectionException $e) {
                throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
            }

            $attributesMetadata = $this->getPropertyAttributesMetadata($propertyReflection);
            $encodedName = $attributesMetadata['name'] ?? $initialEncodedName;

            if (null === $valueTransformer = $attributesMetadata['toNativeValueTransformer'] ?? null) {
                $result[$encodedName] = $initialMetadata;

                continue;
            }

            if (\is_string($valueTransformer)) {
                $valueTransformerService = $this->getAndValidateValueTransformerService($valueTransformer);

                $result[$encodedName] = $initialMetadata
                    ->withType($valueTransformerService::getJsonValueType())
                    ->withAdditionalToNativeValueTransformer($valueTransformer);

                continue;
            }

            try {
                $valueTransformerReflection = new \ReflectionFunction($valueTransformer);
            } catch (\ReflectionException $e) {
                throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
            }

            if (null === ($parameterReflection = $valueTransformerReflection->getParameters()[0] ?? null)) {
                throw new InvalidArgumentException(\sprintf('"%s" property\'s toNativeValue callable has no parameter.', $initialEncodedName));
            }

            $result[$encodedName] = $initialMetadata
                ->withType($this->typeResolver->resolve($parameterReflection))
                ->withAdditionalToNativeValueTransformer($valueTransformer);
        }

        return $result;
    }

    /**
     * @return array{name?: string, toNativeValueTransformer?: string|\Closure}
     */
    private function getPropertyAttributesMetadata(\ReflectionProperty $reflectionProperty): array
    {
        $metadata = [];

        $reflectionAttribute = $reflectionProperty->getAttributes(EncodedName::class, \ReflectionAttribute::IS_INSTANCEOF)[0] ?? null;
        if (null !== $reflectionAttribute) {
            $metadata['name'] = $reflectionAttribute->newInstance()->getName();
        }

        $reflectionAttribute = $reflectionProperty->getAttributes(ValueTransformer::class, \ReflectionAttribute::IS_INSTANCEOF)[0] ?? null;
        if (null !== $reflectionAttribute) {
            $metadata['toNativeValueTransformer'] = $reflectionAttribute->newInstance()->getToNativeValueTransformer();
        }

        return $metadata;
    }

    private function getAndValidateValueTransformerService(string $valueTransformerId): ValueTransformerInterface
    {
        if (!$this->valueTransformers->has($valueTransformerId)) {
            throw new InvalidArgumentException(\sprintf('You have requested a non-existent value transformer service "%s". Did you implement "%s"?', $valueTransformerId, ValueTransformerInterface::class));
        }

        $valueTransformer = $this->valueTransformers->get($valueTransformerId);
        if (!$valueTransformer instanceof ValueTransformerInterface) {
            throw new InvalidArgumentException(\sprintf('The "%s" value transformer service does not implement "%s".', $valueTransformerId, ValueTransformerInterface::class));
        }

        return $valueTransformer;
    }
}
