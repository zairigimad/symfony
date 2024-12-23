<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\app\JsonEncoder\Dto;

use Symfony\Bundle\FrameworkBundle\Tests\Functional\app\JsonEncoder\RangeToStringValueTransformer;
use Symfony\Bundle\FrameworkBundle\Tests\Functional\app\JsonEncoder\StringToRangeValueTransformer;
use Symfony\Component\JsonEncoder\Attribute\EncodedName;
use Symfony\Component\JsonEncoder\Attribute\JsonEncodable;
use Symfony\Component\JsonEncoder\Attribute\ValueTransformer;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
#[JsonEncodable]
class Dummy
{
    #[EncodedName('@name')]
    #[ValueTransformer(toJsonValue: 'strtoupper', toNativeValue: 'strtolower')]
    public string $name = 'dummy';

    #[ValueTransformer(toJsonValue: RangeToStringValueTransformer::class, toNativeValue: StringToRangeValueTransformer::class)]
    public array $range = [10, 20];
}
