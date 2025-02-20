<?php

namespace Symfony\Component\JsonEncoder\Tests\Fixtures\Model;

use Symfony\Component\JsonEncoder\Attribute\ValueTransformer;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Attribute\BooleanStringValueTransformer;
use Symfony\Component\JsonEncoder\Tests\Fixtures\ValueTransformer\DivideStringAndCastToIntValueTransformer;
use Symfony\Component\JsonEncoder\Tests\Fixtures\ValueTransformer\DoubleIntAndCastToStringValueTransformer;

class DummyWithValueTransformerAttributes
{
    #[ValueTransformer(
        toJsonValue: DoubleIntAndCastToStringValueTransformer::class,
        toNativeValue: DivideStringAndCastToIntValueTransformer::class,
    )]
    public int $id = 1;

    #[BooleanStringValueTransformer]
    public bool $active = false;

    #[ValueTransformer(toJsonValue: 'strtolower', toNativeValue: 'strtoupper')]
    public string $name = 'DUMMY';

    #[ValueTransformer(
        toJsonValue: [self::class, 'concatRange'],
        toNativeValue: [self::class, 'explodeRange'],
    )]
    public array $range = [10, 20];

    /**
     * @param array{0: int, 1: int} $range
     */
    public static function concatRange(array $range): string
    {
        return $range[0].'..'.$range[1];
    }

    /**
     * @return array{0: int, 1: int}
     */
    public static function explodeRange(string $range): array
    {
        return array_map(static fn (string $v): int => (int) $v, explode('..', $range));
    }
}
