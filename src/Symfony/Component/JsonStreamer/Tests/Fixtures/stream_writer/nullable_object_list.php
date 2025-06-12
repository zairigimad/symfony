<?php

/**
 * @param array<int,Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNameAttributes>|null $data
 */
return static function (mixed $data, \Psr\Container\ContainerInterface $valueTransformers, array $options): \Traversable {
    try {
        if (\is_array($data)) {
            yield '[';
            $prefix = '';
            foreach ($data as $value) {
                yield $prefix;
                yield '{"@id":';
                yield \json_encode($value->id, \JSON_THROW_ON_ERROR, 510);
                yield ',"name":';
                yield \json_encode($value->name, \JSON_THROW_ON_ERROR, 510);
                yield '}';
                $prefix = ',';
            }
            yield ']';
        } elseif (null === $data) {
            yield 'null';
        } else {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value.', \get_debug_type($data)));
        }
    } catch (\JsonException $e) {
        throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException($e->getMessage(), 0, $e);
    }
};
