<?php

namespace Symfony\Component\JsonEncoder\Tests\Fixtures\ValueTransformer;

use Symfony\Component\JsonEncoder\ValueTransformer\ValueTransformerInterface;
use Symfony\Component\TypeInfo\Type;

final class BooleanToStringValueTransformer implements ValueTransformerInterface
{
    public function transform(mixed $value, array $options = []): mixed
    {
        return $value ? 'true' : 'false';
    }

    public static function getJsonValueType(): Type
    {
        return Type::string();
    }
}
