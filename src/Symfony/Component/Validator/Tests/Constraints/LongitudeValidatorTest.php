<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use Symfony\Component\Validator\Constraints\Longitude;
use Symfony\Component\Validator\Constraints\LongitudeValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class LongitudeValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): LongitudeValidator
    {
        return new LongitudeValidator();
    }

    /**
     * @dataProvider getValidValues
     */
    public function testLongitudeIsValid($value)
    {
        $this->validator->validate($value, new Longitude());

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getInvalidValues
     */
    public function testInvalidValues($value)
    {
        $constraint = new Longitude(message: 'myMessageTest');

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessageTest')
            ->setParameter('{{ value }}', '"'.$value.'"')
            ->setCode(Longitude::INVALID_LONGITUDE_ERROR)
            ->assertRaised();
    }

    public static function getValidValues()
    {
        return [
            [null],
            [''],
            ['0'],
            [0],
            ['180'],
            [180],
            ['-180'],
            [-180],
            ['179.9999'],
            [-179.9999],
            ['90'],
            ['-90'],
            ['45.123456'],
            ['+45.0'],
            ['+0'],
            ['+180.0'],
            ['-0.0'],
        ];
    }

    public static function getInvalidValues()
    {
        return [
            ['180.0001'],
            ['-180.0001'],
            ['200'],
            [-200],
            ['abc'],
            ['--45'],
            ['+'],
            [' '],
            ['+180.1'],
            ['-181'],
            ['1,23'],
            ['179,999'],
        ];
    }
}
