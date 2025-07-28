<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper;

use Psr\Container\ContainerInterface;
use Symfony\Component\ObjectMapper\Exception\MappingException;
use Symfony\Component\ObjectMapper\Exception\MappingTransformException;
use Symfony\Component\ObjectMapper\Exception\NoSuchPropertyException;
use Symfony\Component\ObjectMapper\Metadata\Mapping;
use Symfony\Component\ObjectMapper\Metadata\ObjectMapperMetadataFactoryInterface;
use Symfony\Component\ObjectMapper\Metadata\ReflectionObjectMapperMetadataFactory;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException as PropertyAccessorNoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\VarExporter\LazyObjectInterface;

/**
 * Object to object mapper.
 *
 * @experimental
 *
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
final class ObjectMapper implements ObjectMapperInterface, ObjectMapperAwareInterface
{
    /**
     * Tracks recursive references.
     */
    private ?\SplObjectStorage $objectMap = null;

    public function __construct(
        private readonly ObjectMapperMetadataFactoryInterface $metadataFactory = new ReflectionObjectMapperMetadataFactory(),
        private readonly ?PropertyAccessorInterface $propertyAccessor = null,
        private readonly ?ContainerInterface $transformCallableLocator = null,
        private readonly ?ContainerInterface $conditionCallableLocator = null,
        private ?ObjectMapperInterface $objectMapper = null,
    ) {
    }

    public function map(object $source, object|string|null $target = null): object
    {
        $objectMapInitialized = false;
        if (null === $this->objectMap) {
            $this->objectMap = new \SplObjectStorage();
            $objectMapInitialized = true;
        }

        $metadata = $this->metadataFactory->create($source);
        $map = $this->getMapTarget($metadata, null, $source, null);
        $target ??= $map?->target;
        $mappingToObject = \is_object($target);

        if (!$target) {
            throw new MappingException(\sprintf('Mapping target not found for source "%s".', get_debug_type($source)));
        }

        if (\is_string($target) && !class_exists($target)) {
            throw new MappingException(\sprintf('Mapping target class "%s" does not exist for source "%s".', $target, get_debug_type($source)));
        }

        try {
            $targetRefl = new \ReflectionClass($target);
        } catch (\ReflectionException $e) {
            throw new MappingException($e->getMessage(), $e->getCode(), $e);
        }

        $mappedTarget = $mappingToObject ? $target : $targetRefl->newInstanceWithoutConstructor();
        if ($map && $map->transform) {
            $mappedTarget = $this->applyTransforms($map, $mappedTarget, $source, null);

            if (!\is_object($mappedTarget)) {
                throw new MappingTransformException(\sprintf('Cannot map "%s" to a non-object target of type "%s".', get_debug_type($source), get_debug_type($mappedTarget)));
            }
        }

        if (!is_a($mappedTarget, $targetRefl->getName(), false)) {
            throw new MappingException(\sprintf('Expected the mapped object to be an instance of "%s" but got "%s".', $targetRefl->getName(), get_debug_type($mappedTarget)));
        }

        $this->objectMap[$source] = $mappedTarget;
        $ctorArguments = [];
        $constructor = $targetRefl->getConstructor();
        foreach ($constructor?->getParameters() ?? [] as $parameter) {
            if (!$parameter->isPromoted()) {
                continue;
            }

            $parameterName = $parameter->getName();
            $property = $targetRefl->getProperty($parameterName);

            if ($property->isReadOnly() && $property->isInitialized($mappedTarget)) {
                continue;
            }

            // this may be filled later on see storeValue
            $ctorArguments[$parameterName] = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
        }

        $readMetadataFrom = $source;
        $refl = $this->getSourceReflectionClass($source, $targetRefl);

        // When source contains no metadata, we read metadata on the target instead
        if ($refl === $targetRefl) {
            $readMetadataFrom = $mappedTarget;
        }

        $mapToProperties = [];
        foreach ($refl->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $propertyName = $property->getName();
            $mappings = $this->metadataFactory->create($readMetadataFrom, $propertyName);
            foreach ($mappings as $mapping) {
                $sourcePropertyName = $propertyName;
                if ($mapping->source && (!$refl->hasProperty($propertyName) || !isset($source->$propertyName))) {
                    $sourcePropertyName = $mapping->source;
                }

                if (false === $if = $mapping->if) {
                    continue;
                }

                $value = $this->getRawValue($source, $sourcePropertyName);
                if ($if && ($fn = $this->getCallable($if, $this->conditionCallableLocator)) && !$this->call($fn, $value, $source, $mappedTarget)) {
                    continue;
                }

                $targetPropertyName = $mapping->target ?? $propertyName;
                if (!$targetRefl->hasProperty($targetPropertyName)) {
                    continue;
                }

                $value = $this->getSourceValue($source, $mappedTarget, $value, $this->objectMap, $mapping);
                $this->storeValue($targetPropertyName, $mapToProperties, $ctorArguments, $value);
            }

            if (!$mappings && $targetRefl->hasProperty($propertyName)) {
                $sourceProperty = $refl->getProperty($propertyName);
                if ($refl->isInstance($source) && !$sourceProperty->isInitialized($source)) {
                    continue;
                }

                $value = $this->getSourceValue($source, $mappedTarget, $this->getRawValue($source, $propertyName), $this->objectMap);
                $this->storeValue($propertyName, $mapToProperties, $ctorArguments, $value);
            }
        }

        if (!$mappingToObject && !$map?->transform && $constructor) {
            try {
                $mappedTarget->__construct(...$ctorArguments);
            } catch (\ReflectionException $e) {
                throw new MappingException($e->getMessage(), $e->getCode(), $e);
            }
        }

        if ($mappingToObject && $ctorArguments) {
            foreach ($ctorArguments as $property => $value) {
                if ($targetRefl->hasProperty($property) && $targetRefl->getProperty($property)->isPublic()) {
                    $mapToProperties[$property] = $value;
                }
            }
        }

        foreach ($mapToProperties as $property => $value) {
            $this->propertyAccessor ? $this->propertyAccessor->setValue($mappedTarget, $property, $value) : ($mappedTarget->{$property} = $value);
        }

        if ($objectMapInitialized) {
            $this->objectMap = null;
        }

        return $mappedTarget;
    }

    private function getRawValue(object $source, string $propertyName): mixed
    {
        if ($this->propertyAccessor) {
            try {
                return $this->propertyAccessor->getValue($source, $propertyName);
            } catch (PropertyAccessorNoSuchPropertyException $e) {
                throw new NoSuchPropertyException($e->getMessage(), $e->getCode(), $e);
            }
        }

        if (!property_exists($source, $propertyName) && !isset($source->{$propertyName})) {
            throw new NoSuchPropertyException(\sprintf('The property "%s" does not exist on "%s".', $propertyName, get_debug_type($source)));
        }

        return $source->{$propertyName};
    }

    private function getSourceValue(object $source, object $target, mixed $value, \SplObjectStorage $objectMap, ?Mapping $mapping = null): mixed
    {
        if ($mapping?->transform) {
            $value = $this->applyTransforms($mapping, $value, $source, $target);
        }

        if (
            \is_object($value)
            && ($innerMetadata = $this->metadataFactory->create($value))
            && ($mapTo = $this->getMapTarget($innerMetadata, $value, $source, $target))
            && (\is_string($mapTo->target) && class_exists($mapTo->target))
        ) {
            $value = $this->applyTransforms($mapTo, $value, $source, $target);

            if ($value === $source) {
                $value = $target;
            } elseif ($objectMap->contains($value)) {
                $value = $objectMap[$value];
            } else {
                $value = ($this->objectMapper ?? $this)->map($value, $mapTo->target);
            }
        }

        return $value;
    }

    /**
     * Store the value either the constructor arguments or as a property to be mapped.
     *
     * @param array<string, mixed> $mapToProperties
     * @param array<string, mixed> $ctorArguments
     */
    private function storeValue(string $propertyName, array &$mapToProperties, array &$ctorArguments, mixed $value): void
    {
        if (\array_key_exists($propertyName, $ctorArguments)) {
            $ctorArguments[$propertyName] = $value;

            return;
        }

        $mapToProperties[$propertyName] = $value;
    }

    /**
     * @param callable(): mixed $fn
     */
    private function call(callable $fn, mixed $value, object $source, ?object $target = null): mixed
    {
        if (\is_string($fn)) {
            return \call_user_func($fn, $value);
        }

        return $fn($value, $source, $target);
    }

    /**
     * @param Mapping[] $metadata
     */
    private function getMapTarget(array $metadata, mixed $value, object $source, ?object $target): ?Mapping
    {
        $mapTo = null;
        foreach ($metadata as $mapAttribute) {
            if (($if = $mapAttribute->if) && ($fn = $this->getCallable($if, $this->conditionCallableLocator)) && !$this->call($fn, $value, $source, $target)) {
                continue;
            }

            $mapTo = $mapAttribute;
        }

        return $mapTo;
    }

    private function applyTransforms(Mapping $map, mixed $value, object $source, ?object $target): mixed
    {
        if (!$transforms = $map->transform) {
            return $value;
        }

        if (\is_callable($transforms)) {
            $transforms = [$transforms];
        } elseif (!\is_array($transforms)) {
            $transforms = [$transforms];
        }

        foreach ($transforms as $transform) {
            if ($fn = $this->getCallable($transform, $this->transformCallableLocator)) {
                $value = $this->call($fn, $value, $source, $target);
            }
        }

        return $value;
    }

    /**
     * @param (string|callable(mixed $value, object $object): mixed) $fn
     */
    private function getCallable(string|callable $fn, ?ContainerInterface $locator = null): ?callable
    {
        if (\is_callable($fn)) {
            return $fn;
        }

        if ($locator?->has($fn)) {
            return $locator->get($fn);
        }

        return null;
    }

    /**
     * @param \ReflectionClass<object> $targetRefl
     *
     * @return \ReflectionClass<object|T>
     */
    private function getSourceReflectionClass(object $source, \ReflectionClass $targetRefl): \ReflectionClass
    {
        $metadata = $this->metadataFactory->create($source);
        try {
            $refl = new \ReflectionClass($source);
        } catch (\ReflectionException $e) {
            throw new MappingException($e->getMessage(), $e->getCode(), $e);
        }

        if ($source instanceof LazyObjectInterface) {
            $source->initializeLazyObject();
        } elseif ($refl->isUninitializedLazyObject($source)) {
            $refl->initializeLazyObject($source);
        }

        if ($metadata) {
            return $refl;
        }

        foreach ($refl->getProperties() as $property) {
            if ($this->metadataFactory->create($source, $property->getName())) {
                return $refl;
            }
        }

        return $targetRefl;
    }

    public function withObjectMapper(ObjectMapperInterface $objectMapper): static
    {
        $clone = clone $this;
        $clone->objectMapper = $objectMapper;

        return $clone;
    }
}
