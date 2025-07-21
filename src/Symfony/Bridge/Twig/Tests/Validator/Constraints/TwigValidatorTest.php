<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Validator\Constraints;

use Symfony\Bridge\Twig\Validator\Constraints\Twig;
use Symfony\Bridge\Twig\Validator\Constraints\TwigValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Twig\DeprecatedCallableInfo;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFilter;

/**
 * @author Mokhtar Tlili <tlili.mokhtar@gmail.com>
 */
class TwigValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): TwigValidator
    {
        $environment = new Environment(new ArrayLoader());
        $environment->addFilter(new TwigFilter('humanize_filter', fn ($v) => $v));
        $options = ['deprecation_info' => new DeprecatedCallableInfo('foo/bar', '1.1')];

        $environment->addFilter(new TwigFilter('deprecated_filter', fn ($v) => $v, $options));

        return new TwigValidator($environment);
    }

    /**
     * @dataProvider getValidValues
     */
    public function testTwigIsValid($value)
    {
        $this->validator->validate($value, new Twig());

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getInvalidValues
     */
    public function testInvalidValues($value, $message, $line)
    {
        $constraint = new Twig('myMessageTest');

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessageTest')
            ->setParameter('{{ error }}', $message)
            ->setParameter('{{ line }}', $line)
            ->setCode(Twig::INVALID_TWIG_ERROR)
            ->assertRaised();
    }

    /**
     * When deprecations are skipped by the validator, the testsuite reporter will catch them so we need to mark the test as legacy.
     *
     * @group legacy
     */
    public function testTwigWithSkipDeprecation()
    {
        $constraint = new Twig(skipDeprecations: true);

        $this->validator->validate('{{ name|deprecated_filter }}', $constraint);

        $this->assertNoViolation();
    }

    public function testTwigWithoutSkipDeprecation()
    {
        $constraint = new Twig(skipDeprecations: false);

        $this->validator->validate('{{ name|deprecated_filter }}', $constraint);

        $this->buildViolation($constraint->message)
            ->setParameter('{{ error }}', 'Since foo/bar 1.1: Twig Filter "deprecated_filter" is deprecated.')
            ->setParameter('{{ line }}', 0)
            ->setCode(Twig::INVALID_TWIG_ERROR)
            ->assertRaised();
    }

    public static function getValidValues()
    {
        return [
            ['Hello {{ name }}'],
            ['{% if condition %}Yes{% else %}No{% endif %}'],
            ['{# Comment #}'],
            ['Hello {{ "world"|upper }}'],
            ['{% for i in 1..3 %}Item {{ i }}{% endfor %}'],
            ['{{ name|humanize_filter }}'],
        ];
    }

    public static function getInvalidValues()
    {
        return [
            // Invalid syntax example (missing end tag)
            ['{% if condition %}Oops', 'Unexpected end of template at line 1.', 1],
            // Another syntax error example (unclosed variable)
            ['Hello {{ name', 'Unexpected token "end of template" ("end of print statement" expected) at line 1.', 1],
            // Unknown filter error
            ['Hello {{ name|unknown_filter }}', 'Unknown "unknown_filter" filter at line 1.', 1],
            // Invalid variable syntax
            ['Hello {{ .name }}', 'Unexpected token "operator" of value "." at line 1.', 1],
        ];
    }
}
