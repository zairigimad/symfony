<?php

namespace Symfony\Component\JsonEncoder\Tests\Fixtures\Attribute;

use Symfony\Component\JsonEncoder\Attribute\ValueTransformer;
use Symfony\Component\JsonEncoder\Tests\Fixtures\ValueTransformer\BooleanToStringValueTransformer;
use Symfony\Component\JsonEncoder\Tests\Fixtures\ValueTransformer\StringToBooleanValueTransformer;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class BooleanStringValueTransformer extends ValueTransformer
{
    public function __construct()
    {
        parent::__construct(toJsonValue: BooleanToStringValueTransformer::class, toNativeValue: StringToBooleanValueTransformer::class);
    }
}
