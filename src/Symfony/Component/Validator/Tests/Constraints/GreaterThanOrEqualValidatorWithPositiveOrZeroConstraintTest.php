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

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqualValidator;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

/**
 * @author Jan Schädlich <jan.schaedlich@sensiolabs.de>
 */
class GreaterThanOrEqualValidatorWithPositiveOrZeroConstraintTest extends AbstractComparisonValidatorTestCase
{
    protected function createValidator(): GreaterThanOrEqualValidator
    {
        return new GreaterThanOrEqualValidator();
    }

    protected static function createConstraint(?array $options = null): Constraint
    {
        return new PositiveOrZero($options);
    }

    public static function provideValidComparisons(): array
    {
        return [
            [0, 0],
            [1, 0],
            [2, 0],
            [2.5, 0],
            ['0', '0'],
            ['333', '0'],
            [null, 0],
        ];
    }

    public static function provideValidComparisonsToPropertyPath(): array
    {
        return [
            [5],
            [6],
        ];
    }

    public static function provideInvalidComparisons(): array
    {
        return [
            [-1, '-1', 0, '0', 'int'],
            [-2, '-2', 0, '0', 'int'],
            [-2.5, '-2.5', 0, '0', 'int'],
        ];
    }

    /**
     * @dataProvider provideInvalidConstraintOptions
     */
    public function testThrowsConstraintExceptionIfNoValueOrPropertyPath($options)
    {
        $this->markTestSkipped('Value option always set for PositiveOrZero constraint');
    }

    public function testThrowsConstraintExceptionIfBothValueAndPropertyPath()
    {
        $this->markTestSkipped('Value option is set for PositiveOrZero constraint automatically');
    }

    public function testInvalidValuePath()
    {
        $this->markTestSkipped('PropertyPath option is not used in PositiveOrZero constraint');
    }

    /**
     * @dataProvider provideValidComparisonsToPropertyPath
     */
    public function testValidComparisonToPropertyPath($comparedValue)
    {
        $this->markTestSkipped('PropertyPath option is not used in PositiveOrZero constraint');
    }

    public function testNoViolationOnNullObjectWithPropertyPath()
    {
        $this->markTestSkipped('PropertyPath option is not used in PositiveOrZero constraint');
    }

    public function testInvalidComparisonToPropertyPathAddsPathAsParameter()
    {
        $this->markTestSkipped('PropertyPath option is not used in PositiveOrZero constraint');
    }
}
