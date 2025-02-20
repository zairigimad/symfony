<?php

namespace Symfony\Component\JsonEncoder\Tests\Fixtures\ValueTransformer;

use Symfony\Component\JsonEncoder\ValueTransformer\ValueTransformerInterface;
use Symfony\Component\TypeInfo\Type;

final class StringToBooleanValueTransformer implements ValueTransformerInterface
{
    public function transform(mixed $value, array $options = []): mixed
    {
        return 'true' === $value;
    }

    public static function getJsonValueType(): Type
    {
        return Type::string();
    }
}
