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

use Symfony\Component\Validator\Constraints\Latitude;
use Symfony\Component\Validator\Constraints\LatitudeValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class LatitudeValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): LatitudeValidator
    {
        return new LatitudeValidator();
    }

    /**
     * @dataProvider getValidValues
     */
    public function testLatitudeIsValid($value)
    {
        $this->validator->validate($value, new Latitude());

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getInvalidValues
     */
    public function testInvalidValues($value)
    {
        $constraint = new Latitude(message: 'myMessageTest');

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessageTest')
            ->setParameter('{{ value }}', $value)
            ->setCode(Latitude::INVALID_LATITUDE_ERROR)
            ->assertRaised();
    }

    public static function getValidValues()
    {
        return [
            [null],
            [''],
            ['0'],
            [0],
            ['90'],
            [90],
            ['-90'],
            [-90],
            ['89.9999'],
            [-89.9999],
            ['45.123456'],
            [33.975738401584266],
            ['+45.0'],
            ['+0'],
            ['+90.0'],
            ['-0.0'],
            ['0.0'],
            ['45'],
            ['-45'],
            ['89'],
            ['-89'],
        ];
    }

    public static function getInvalidValues()
    {
        return [
            ['90.0001'],
            ['-90.0001'],
            ['91'],
            [-91],
            ['180'],
            ['-180'],
            ['200'],
            [-200],
            ['abc'],
            ['--45'],
            ['+'],
            [' '],
            ['+90.1'],
            ['-91'],
            ['1,23'],
            ['89,999'],
        ];
    }
}
