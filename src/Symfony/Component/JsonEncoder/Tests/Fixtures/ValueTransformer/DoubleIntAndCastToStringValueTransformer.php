<?php

namespace Symfony\Component\JsonEncoder\Tests\Fixtures\ValueTransformer;

use Symfony\Component\JsonEncoder\ValueTransformer\ValueTransformerInterface;
use Symfony\Component\TypeInfo\Type;

final class DoubleIntAndCastToStringValueTransformer implements ValueTransformerInterface
{
    public function transform(mixed $value, array $options = []): mixed
    {
        return (string) (2 * $options['scale'] * $value);
    }

    public static function getJsonValueType(): Type
    {
        return Type::string();
    }
}
