<?php

namespace Symfony\Component\JsonEncoder\Tests\Fixtures\ValueTransformer;

use Symfony\Component\JsonEncoder\ValueTransformer\ValueTransformerInterface;
use Symfony\Component\TypeInfo\Type;

final class DivideStringAndCastToIntValueTransformer implements ValueTransformerInterface
{
    public function transform(mixed $value, array $options = []): mixed
    {
        return (int) (((int) $value) / (2 * $options['scale']));
    }

    public static function getJsonValueType(): Type
    {
        return Type::string();
    }
}
