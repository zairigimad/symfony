<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarExporter\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\VarExporter\Exception\LogicException;
use Symfony\Component\VarExporter\ProxyHelper;
use Symfony\Component\VarExporter\Tests\Fixtures\LazyGhost\RegularClass;
use Symfony\Component\VarExporter\Tests\Fixtures\LazyProxy\AbstractHooked;
use Symfony\Component\VarExporter\Tests\Fixtures\LazyProxy\AsymmetricVisibility;
use Symfony\Component\VarExporter\Tests\Fixtures\LazyProxy\ConcreteReadOnlyClass;
use Symfony\Component\VarExporter\Tests\Fixtures\LazyProxy\FinalPublicClass;
use Symfony\Component\VarExporter\Tests\Fixtures\LazyProxy\Hooked;
use Symfony\Component\VarExporter\Tests\Fixtures\LazyProxy\ReadOnlyClass;
use Symfony\Component\VarExporter\Tests\Fixtures\LazyProxy\StringMagicGetClass;
use Symfony\Component\VarExporter\Tests\Fixtures\LazyProxy\TestClass;
use Symfony\Component\VarExporter\Tests\Fixtures\LazyProxy\TestOverwritePropClass;
use Symfony\Component\VarExporter\Tests\Fixtures\LazyProxy\TestUnserializeClass;
use Symfony\Component\VarExporter\Tests\Fixtures\LazyProxy\TestWakeupClass;
use Symfony\Component\VarExporter\Tests\Fixtures\SimpleObject;

class LazyProxyTraitTest extends TestCase
{
    public function testGetter()
    {
        $initCounter = 0;
        $proxy = $this->createLazyProxy(TestClass::class, function () use (&$initCounter) {
            ++$initCounter;

            return new TestClass((object) ['hello' => 'world']);
        });

        $this->assertInstanceOf(TestClass::class, $proxy);
        $this->assertSame(0, $initCounter);
        $this->assertFalse($proxy->isLazyObjectInitialized());

        $dep1 = $proxy->getDep();
        $this->assertTrue($proxy->isLazyObjectInitialized());
        $this->assertSame(1, $initCounter);

        $this->assertTrue($proxy->resetLazyObject());
        $this->assertSame(1, $initCounter);

        $dep2 = $proxy->getDep();
        $this->assertSame(2, $initCounter);
        $this->assertNotSame($dep1, $dep2);
    }

    public function testInitialize()
    {
        $initCounter = 0;
        $proxy = $this->createLazyProxy(TestClass::class, function () use (&$initCounter) {
            ++$initCounter;

            return new TestClass((object) ['hello' => 'world']);
        });

        $this->assertSame(0, $initCounter);
        $this->assertFalse($proxy->isLazyObjectInitialized());

        $proxy->initializeLazyObject();
        $this->assertTrue($proxy->isLazyObjectInitialized());
        $this->assertSame(1, $initCounter);

        $proxy->initializeLazyObject();
        $this->assertSame(1, $initCounter);
    }

    public function testClone()
    {
        $initCounter = 0;
        $proxy = $this->createLazyProxy(TestClass::class, function () use (&$initCounter) {
            ++$initCounter;

            return new TestClass((object) ['hello' => 'world']);
        });

        $clone = clone $proxy;
        $this->assertSame(1, $initCounter);

        $dep1 = $proxy->getDep();
        $this->assertSame(1, $initCounter);

        $dep2 = $clone->getDep();
        $this->assertSame(1, $initCounter);

        $this->assertSame($dep1, $dep2);
    }

    public function testUnserialize()
    {
        $initCounter = 0;
        $proxy = $this->createLazyProxy(TestUnserializeClass::class, function () use (&$initCounter) {
            ++$initCounter;

            return new TestUnserializeClass((object) ['hello' => 'world']);
        });

        $this->assertInstanceOf(TestUnserializeClass::class, $proxy);
        $this->assertSame(0, $initCounter);

        $copy = unserialize(serialize($proxy));
        $this->assertSame(1, $initCounter);
        $this->assertTrue($copy->isLazyObjectInitialized());
        $this->assertTrue($proxy->isLazyObjectInitialized());

        $this->assertFalse($copy->resetLazyObject());
        $this->assertTrue($copy->getDep()->wokeUp);
        $this->assertSame('world', $copy->getDep()->hello);
    }

    public function testWakeup()
    {
        $initCounter = 0;
        $proxy = $this->createLazyProxy(TestWakeupClass::class, function () use (&$initCounter) {
            ++$initCounter;

            return new TestWakeupClass((object) ['hello' => 'world']);
        });

        $this->assertInstanceOf(TestWakeupClass::class, $proxy);
        $this->assertSame(0, $initCounter);

        $copy = unserialize(serialize($proxy));
        $this->assertSame(1, $initCounter);

        $this->assertFalse($copy->resetLazyObject());
        $this->assertTrue($copy->getDep()->wokeUp);
        $this->assertSame('world', $copy->getDep()->hello);
    }

    public function testDestruct()
    {
        $initCounter = 0;
        $proxy = $this->createLazyProxy(TestClass::class, function () use (&$initCounter) {
            ++$initCounter;

            return new TestClass((object) ['hello' => 'world']);
        });

        unset($proxy);
        $this->assertSame(0, $initCounter);

        $proxy = $this->createLazyProxy(TestClass::class, function () use (&$initCounter) {
            ++$initCounter;

            return new TestClass((object) ['hello' => 'world']);
        });
        $dep = $proxy->getDep();
        $this->assertSame(1, $initCounter);
        unset($proxy);
        $this->assertTrue($dep->destructed);
    }

    public function testDynamicProperty()
    {
        $initCounter = 0;
        $proxy = $this->createLazyProxy(TestClass::class, function () use (&$initCounter) {
            ++$initCounter;

            return new TestClass((object) ['hello' => 'world']);
        });

        $proxy->dynProp = 123;
        $this->assertSame(1, $initCounter);
        $this->assertSame(123, $proxy->dynProp);
        $this->assertTrue(isset($proxy->dynProp));
        $this->assertCount(1, (array) $proxy);
        unset($proxy->dynProp);
        $this->assertFalse(isset($proxy->dynProp));
        $this->assertCount(1, (array) $proxy);
    }

    public function testStringMagicGet()
    {
        $proxy = $this->createLazyProxy(StringMagicGetClass::class, fn () => new StringMagicGetClass());

        $this->assertSame('abc', $proxy->abc);
    }

    public function testFinalPublicClass()
    {
        $this->expectException(LogicException::class, 'Cannot generate lazy proxy: method "Symfony\Component\VarExporter\Tests\Fixtures\LazyProxy\FinalPublicClass::increment()" is final.');
        $this->createLazyProxy(FinalPublicClass::class, fn () => new FinalPublicClass());
    }

    public function testOverwritePropClass()
    {
        $this->expectException(LogicException::class, 'Cannot generate lazy proxy: method "Symfony\Component\VarExporter\Tests\Fixtures\LazyProxy\FinalPublicClass::increment()" is final.');
        $this->createLazyProxy(TestOverwritePropClass::class, fn () => new TestOverwritePropClass('123', 5));
    }

    public function testWither()
    {
        $obj = new class extends \stdClass {
            public $foo = 123;

            public function withFoo($foo): static
            {
                $clone = clone $this;
                $clone->foo = $foo;

                return $clone;
            }
        };
        $proxy = $this->createLazyProxy($obj::class, fn () => $obj);

        $clone = $proxy->withFoo(234);
        $this->assertSame($clone::class, $proxy::class);
        $this->assertSame(234, $clone->foo);
        $this->assertSame(123, $obj->foo);
    }

    public function testFluent()
    {
        $obj = new class extends \stdClass {
            public $foo = 123;

            public function setFoo($foo): static
            {
                $this->foo = $foo;

                return $this;
            }
        };
        $proxy = $this->createLazyProxy($obj::class, fn () => $obj);

        $this->assertSame($proxy->setFoo(234), $proxy);
        $this->assertSame(234, $proxy->foo);
    }

    public function testIndirectModification()
    {
        $obj = new class extends \stdClass {
            public array $foo;
        };
        $proxy = $this->createLazyProxy($obj::class, fn () => $obj);

        $proxy->foo[] = 123;

        $this->assertSame([123], $proxy->foo);
    }

    public function testReadOnlyClass()
    {
        $proxy = $this->createLazyProxy(ReadOnlyClass::class, fn () => new ConcreteReadOnlyClass(123));

        $this->assertSame(123, $proxy->foo);
    }

    public function testNormalization()
    {
        $object = $this->createLazyProxy(SimpleObject::class, fn () => new SimpleObject());

        $loader = new AttributeLoader();
        $metadataFactory = new ClassMetadataFactory($loader);
        $serializer = new ObjectNormalizer($metadataFactory);

        $output = $serializer->normalize($object);

        $this->assertSame(['property' => 'property', 'method' => 'method'], $output);
    }

    public function testReinitRegularLazyProxy()
    {
        $object = $this->createLazyProxy(RegularClass::class, fn () => new RegularClass(123));

        $this->assertSame(123, $object->foo);

        $object::createLazyProxy(fn () => new RegularClass(234), $object);

        $this->assertSame(234, $object->foo);
    }

    public function testReinitReadonlyLazyProxy()
    {
        $object = $this->createLazyProxy(ReadOnlyClass::class, fn () => new ConcreteReadOnlyClass(123));

        $this->assertSame(123, $object->foo);

        $object::createLazyProxy(fn () => new ConcreteReadOnlyClass(234), $object);

        $this->assertSame(234, $object->foo);
    }

    public function testConcretePropertyHooks()
    {
        $initialized = false;
        $object = $this->createLazyProxy(Hooked::class, function () use (&$initialized) {
            $initialized = true;

            return new Hooked();
        });

        $this->assertSame(123, $object->notBacked);
        $this->assertTrue($initialized);
        $this->assertSame(234, $object->backed);
        $this->assertTrue($initialized);

        $initialized = false;
        $object = $this->createLazyProxy(Hooked::class, function () use (&$initialized) {
            $initialized = true;

            return new Hooked();
        });

        $object->backed = 345;
        $this->assertTrue($initialized);
        $this->assertSame(345, $object->backed);
    }

    public function testAbstractPropertyHooks()
    {
        $initialized = false;
        $object = $this->createLazyProxy(AbstractHooked::class, function () use (&$initialized) {
            $initialized = true;

            return new class extends AbstractHooked {
                public string $foo = 'Foo';
                public string $bar = 'Bar';
            };
        });

        $this->assertSame('Foo', $object->foo);
        $this->assertSame('Bar', $object->bar);
        $this->assertTrue($initialized);

        $initialized = false;
        $object = $this->createLazyProxy(AbstractHooked::class, function () use (&$initialized) {
            $initialized = true;

            return new class extends AbstractHooked {
                public string $foo = 'Foo';
                public string $bar = 'Bar';
            };
        });

        $this->assertSame('Bar', $object->bar);
        $this->assertSame('Foo', $object->foo);
        $this->assertTrue($initialized);
    }

    public function testAsymmetricVisibility()
    {
        $object = $this->createLazyProxy(AsymmetricVisibility::class, function () {
            return new AsymmetricVisibility(123, 234);
        });

        $this->assertSame(123, $object->foo);
        $this->assertSame(234, $object->getBar());

        $object = $this->createLazyProxy(AsymmetricVisibility::class, function () {
            return new AsymmetricVisibility(123, 234);
        });

        $this->assertSame(234, $object->getBar());
        $this->assertSame(123, $object->foo);
    }

    public function testInternalClass()
    {
        $now = new \DateTimeImmutable();
        $initialized = false;
        $object = $this->createLazyProxy(\DateTimeImmutable::class, function () use ($now, &$initialized) {
            $initialized = true;

            return $now;
        });

        $this->assertSame(date('Y'), $object->format('Y'));
        $this->assertTrue($initialized);
    }

    /**
     * @template T
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    protected function createLazyProxy(string $class, \Closure $initializer): object
    {
        $r = new \ReflectionClass($class);

        if (str_contains($class, "\0")) {
            $class = __CLASS__.'\\'.debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'].'_L'.$r->getStartLine();
            class_alias($r->name, $class);
        }
        $proxy = str_replace($r->name, $class, ProxyHelper::generateLazyProxy($r));
        $class = str_replace('\\', '_', $class).'_'.md5($proxy);

        if (!class_exists($class, false)) {
            eval(($r->isReadOnly() ? 'readonly ' : '').'class '.$class.' '.$proxy);
        }

        return $class::createLazyProxy($initializer);
    }
}
