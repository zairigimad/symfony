<?php

return static function (mixed $data, \Psr\Container\ContainerInterface $valueTransformers, array $options): \Traversable {
    yield '{"id":';
    yield \json_encode($valueTransformers->get('Symfony\Component\JsonEncoder\Tests\Fixtures\ValueTransformer\DoubleIntAndCastToStringValueTransformer')->transform($data->id, $options));
    yield ',"active":';
    yield \json_encode($valueTransformers->get('Symfony\Component\JsonEncoder\Tests\Fixtures\ValueTransformer\BooleanToStringValueTransformer')->transform($data->active, $options));
    yield ',"name":';
    yield \json_encode(strtolower($data->name));
    yield ',"range":';
    yield \json_encode(Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithValueTransformerAttributes::concatRange($data->range, $options));
    yield '}';
};
