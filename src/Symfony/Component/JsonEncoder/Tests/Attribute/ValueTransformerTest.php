<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonEncoder\Attribute\ValueTransformer;
use Symfony\Component\JsonEncoder\Exception\LogicException;

class ValueTransformerTest extends TestCase
{
    public function testCannotCreateWithoutAnything()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('#[ValueTransformer] attribute must declare either $toNativeValue or $toJsonValue.');

        new ValueTransformer();
    }
}
