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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Longitude;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;

class LongitudeTest extends TestCase
{
    public function testAttributes()
    {
        $metadata = new ClassMetadata(LongitudeDummy::class);
        $loader = new AttributeLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));

        [$bConstraint] = $metadata->properties['b']->getConstraints();
        self::assertSame('myMessage', $bConstraint->message);
        self::assertSame(['Default', 'LongitudeDummy'], $bConstraint->groups);

        [$cConstraint] = $metadata->properties['c']->getConstraints();
        self::assertSame(['my_group'], $cConstraint->groups);
    }
}

class LongitudeDummy
{
    #[Longitude]
    private $a;

    #[Longitude(message: 'myMessage')]
    private $b;

    #[Longitude(groups: ['my_group'])]
    private $c;
}
