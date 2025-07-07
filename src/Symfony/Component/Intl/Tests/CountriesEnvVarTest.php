<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Intl\Countries;

/**
 * @group intl-data
 * @group intl-data-isolate
 */
class CountriesEnvVarTest extends TestCase
{
    public function testWhenEnvVarNotSet(): void
    {
        $this->assertFalse(Countries::exists('XK'));
    }

    public function testWhenEnvVarSetFalse(): void
    {
        putenv('SYMFONY_INTL_WITH_USER_ASSIGNED=false');

        $this->assertFalse(Countries::exists('XK'));
    }

    public function testWhenEnvVarSetTrue(): void
    {
        putenv('SYMFONY_INTL_WITH_USER_ASSIGNED=true');

        $this->assertTrue(Countries::exists('XK'));
    }
}
