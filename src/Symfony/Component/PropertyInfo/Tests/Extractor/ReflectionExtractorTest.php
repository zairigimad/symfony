<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Extractor;

use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyReadInfo;
use Symfony\Component\PropertyInfo\PropertyWriteInfo;
use Symfony\Component\PropertyInfo\Tests\Fixtures\AdderRemoverDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\AsymmetricVisibility;
use Symfony\Component\PropertyInfo\Tests\Fixtures\ConstructorDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\DefaultValue;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\NotInstantiable;
use Symfony\Component\PropertyInfo\Tests\Fixtures\ParentDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Php71Dummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Php71DummyExtended;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Php71DummyExtended2;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Php74Dummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Php7Dummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Php7ParentDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Php80Dummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Php81Dummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\Php82Dummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\SnakeCaseDummy;
use Symfony\Component\PropertyInfo\Tests\Fixtures\VirtualProperties;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\NullableType;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ReflectionExtractorTest extends TestCase
{
    private ReflectionExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new ReflectionExtractor();
    }

    public function testGetProperties()
    {
        $this->assertSame(
            [
                'bal',
                'parent',
                'collection',
                'collectionAsObject',
                'nestedCollection',
                'mixedCollection',
                'B',
                'Guid',
                'g',
                'h',
                'i',
                'j',
                'nullableCollectionOfNonNullableElements',
                'nonNullableCollectionOfNullableElements',
                'nullableCollectionOfMultipleNonNullableElementTypes',
                'emptyVar',
                'iteratorCollection',
                'iteratorCollectionWithKey',
                'nestedIterators',
                'arrayWithKeys',
                'arrayWithKeysAndComplexValue',
                'arrayOfMixed',
                'noDocBlock',
                'listOfStrings',
                'parentAnnotation',
                'genericInterface',
                'nullableTypedCollection',
                'foo',
                'foo2',
                'foo3',
                'foo4',
                'foo5',
                'files',
                'propertyTypeStatic',
                'parentAnnotationNoParent',
                'rootDummyItems',
                'rootDummyItem',
                'a',
                'DOB',
                'Id',
                '123',
                'self',
                'realParent',
                'xTotals',
                'YT',
                'date',
                'element',
                'c',
                'ct',
                'cf',
                'd',
                'dt',
                'df',
                'e',
                'f',
            ],
            $this->extractor->getProperties('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy')
        );

        $this->assertNull($this->extractor->getProperties('Symfony\Component\PropertyInfo\Tests\Fixtures\NoProperties'));
    }

    public function testGetPropertiesWithCustomPrefixes()
    {
        $customExtractor = new ReflectionExtractor(['add', 'remove'], ['is', 'can']);

        $this->assertSame(
            [
                'bal',
                'parent',
                'collection',
                'collectionAsObject',
                'nestedCollection',
                'mixedCollection',
                'B',
                'Guid',
                'g',
                'h',
                'i',
                'j',
                'nullableCollectionOfNonNullableElements',
                'nonNullableCollectionOfNullableElements',
                'nullableCollectionOfMultipleNonNullableElementTypes',
                'emptyVar',
                'iteratorCollection',
                'iteratorCollectionWithKey',
                'nestedIterators',
                'arrayWithKeys',
                'arrayWithKeysAndComplexValue',
                'arrayOfMixed',
                'noDocBlock',
                'listOfStrings',
                'parentAnnotation',
                'genericInterface',
                'nullableTypedCollection',
                'foo',
                'foo2',
                'foo3',
                'foo4',
                'foo5',
                'files',
                'propertyTypeStatic',
                'parentAnnotationNoParent',
                'rootDummyItems',
                'rootDummyItem',
                'date',
                'c',
                'ct',
                'cf',
                'd',
                'dt',
                'df',
                'e',
                'f',
            ],
            $customExtractor->getProperties('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy')
        );
    }

    public function testGetPropertiesWithNoPrefixes()
    {
        $noPrefixExtractor = new ReflectionExtractor([], [], []);

        $this->assertSame(
            [
                'bal',
                'parent',
                'collection',
                'collectionAsObject',
                'nestedCollection',
                'mixedCollection',
                'B',
                'Guid',
                'g',
                'h',
                'i',
                'j',
                'nullableCollectionOfNonNullableElements',
                'nonNullableCollectionOfNullableElements',
                'nullableCollectionOfMultipleNonNullableElementTypes',
                'emptyVar',
                'iteratorCollection',
                'iteratorCollectionWithKey',
                'nestedIterators',
                'arrayWithKeys',
                'arrayWithKeysAndComplexValue',
                'arrayOfMixed',
                'noDocBlock',
                'listOfStrings',
                'parentAnnotation',
                'genericInterface',
                'nullableTypedCollection',
                'foo',
                'foo2',
                'foo3',
                'foo4',
                'foo5',
                'files',
                'propertyTypeStatic',
                'parentAnnotationNoParent',
                'rootDummyItems',
                'rootDummyItem',
            ],
            $noPrefixExtractor->getProperties('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy')
        );
    }

    public function testReadonlyPropertiesAreNotWriteable()
    {
        $this->assertFalse($this->extractor->isWritable(Php81Dummy::class, 'foo'));
    }

    /**
     * @dataProvider getReadableProperties
     */
    public function testIsReadable($property, $expected)
    {
        $this->assertSame(
            $expected,
            $this->extractor->isReadable('Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy', $property, [])
        );
    }

    public static function getReadableProperties()
    {
        return [
            ['bar', false],
            ['baz', false],
            ['parent', true],
            ['a', true],
            ['b', false],
            ['c', true],
            ['d', true],
            ['e', false],
            ['f', false],
            ['Id', true],
            ['id', true],
            ['Guid', true],
            ['guid', false],
            ['element', false],
        ];
    }

    /**
     * @dataProvider getWritableProperties
     */
    public function testIsWritable($property, $expected)
    {
        $this->assertSame(
            $expected,
            $this->extractor->isWritable(Dummy::class, $property, [])
        );
    }

    public static function getWritableProperties()
    {
        return [
            ['bar', false],
            ['baz', false],
            ['parent', true],
            ['a', false],
            ['b', true],
            ['c', false],
            ['d', false],
            ['e', true],
            ['f', true],
            ['Id', false],
            ['Guid', true],
            ['guid', false],
        ];
    }

    public function testIsReadableSnakeCase()
    {
        $this->assertTrue($this->extractor->isReadable(SnakeCaseDummy::class, 'snake_property'));
        $this->assertTrue($this->extractor->isReadable(SnakeCaseDummy::class, 'snake_readonly'));
    }

    public function testIsWriteableSnakeCase()
    {
        $this->assertTrue($this->extractor->isWritable(SnakeCaseDummy::class, 'snake_property'));
        $this->assertFalse($this->extractor->isWritable(SnakeCaseDummy::class, 'snake_readonly'));
        // Ensure that it's still possible to write to the property using the (old) snake name
        $this->assertTrue($this->extractor->isWritable(SnakeCaseDummy::class, 'snake_method'));
    }

    public function testSingularize()
    {
        $this->assertTrue($this->extractor->isWritable(AdderRemoverDummy::class, 'analyses'));
        $this->assertTrue($this->extractor->isWritable(AdderRemoverDummy::class, 'feet'));
        $this->assertEquals(['analyses', 'feet'], $this->extractor->getProperties(AdderRemoverDummy::class));
    }

    public function testPrivatePropertyExtractor()
    {
        $privateExtractor = new ReflectionExtractor(null, null, null, true, ReflectionExtractor::ALLOW_PUBLIC | ReflectionExtractor::ALLOW_PRIVATE | ReflectionExtractor::ALLOW_PROTECTED);
        $properties = $privateExtractor->getProperties(Dummy::class);

        $this->assertContains('bar', $properties);
        $this->assertContains('baz', $properties);

        $this->assertTrue($privateExtractor->isReadable(Dummy::class, 'bar'));
        $this->assertTrue($privateExtractor->isReadable(Dummy::class, 'baz'));

        $protectedExtractor = new ReflectionExtractor(null, null, null, true, ReflectionExtractor::ALLOW_PUBLIC | ReflectionExtractor::ALLOW_PROTECTED);
        $properties = $protectedExtractor->getProperties(Dummy::class);

        $this->assertNotContains('bar', $properties);
        $this->assertContains('baz', $properties);

        $this->assertFalse($protectedExtractor->isReadable(Dummy::class, 'bar'));
        $this->assertTrue($protectedExtractor->isReadable(Dummy::class, 'baz'));
    }

    /**
     * @dataProvider getInitializableProperties
     */
    public function testIsInitializable(string $class, string $property, bool $expected)
    {
        $this->assertSame($expected, $this->extractor->isInitializable($class, $property));
    }

    public static function getInitializableProperties(): array
    {
        return [
            [Php71Dummy::class, 'string', true],
            [Php71Dummy::class, 'intPrivate', true],
            [Php71Dummy::class, 'notExist', false],
            [Php71DummyExtended2::class, 'intWithAccessor', true],
            [Php71DummyExtended2::class, 'intPrivate', false],
            [NotInstantiable::class, 'foo', false],
        ];
    }

    public function testNullOnPrivateProtectedAccessor()
    {
        $barAccessor = $this->extractor->getReadInfo(Dummy::class, 'bar');
        $barMutator = $this->extractor->getWriteInfo(Dummy::class, 'bar');
        $bazAccessor = $this->extractor->getReadInfo(Dummy::class, 'baz');
        $bazMutator = $this->extractor->getWriteInfo(Dummy::class, 'baz');

        $this->assertNull($barAccessor);
        $this->assertEquals(PropertyWriteInfo::TYPE_NONE, $barMutator->getType());
        $this->assertNull($bazAccessor);
        $this->assertEquals(PropertyWriteInfo::TYPE_NONE, $bazMutator->getType());
    }

    /**
     * @dataProvider readAccessorProvider
     */
    public function testGetReadAccessor($class, $property, $found, $type, $name, $visibility, $static)
    {
        $extractor = new ReflectionExtractor(null, null, null, true, ReflectionExtractor::ALLOW_PUBLIC | ReflectionExtractor::ALLOW_PROTECTED | ReflectionExtractor::ALLOW_PRIVATE);
        $readAccessor = $extractor->getReadInfo($class, $property);

        if (!$found) {
            $this->assertNull($readAccessor);

            return;
        }

        $this->assertNotNull($readAccessor);
        $this->assertSame($type, $readAccessor->getType());
        $this->assertSame($name, $readAccessor->getName());
        $this->assertSame($visibility, $readAccessor->getVisibility());
        $this->assertSame($static, $readAccessor->isStatic());
    }

    public static function readAccessorProvider(): array
    {
        return [
            [Dummy::class, 'bar', true, PropertyReadInfo::TYPE_PROPERTY, 'bar', PropertyReadInfo::VISIBILITY_PRIVATE, false],
            [Dummy::class, 'baz', true, PropertyReadInfo::TYPE_PROPERTY, 'baz', PropertyReadInfo::VISIBILITY_PROTECTED, false],
            [Dummy::class, 'bal', true, PropertyReadInfo::TYPE_PROPERTY, 'bal', PropertyReadInfo::VISIBILITY_PUBLIC, false],
            [Dummy::class, 'parent', true, PropertyReadInfo::TYPE_PROPERTY, 'parent', PropertyReadInfo::VISIBILITY_PUBLIC, false],
            [Dummy::class, 'static', true, PropertyReadInfo::TYPE_METHOD, 'getStatic', PropertyReadInfo::VISIBILITY_PUBLIC, true],
            [Dummy::class, 'foo', true, PropertyReadInfo::TYPE_PROPERTY, 'foo', PropertyReadInfo::VISIBILITY_PUBLIC, false],
            [Php71Dummy::class, 'foo', true, PropertyReadInfo::TYPE_METHOD, 'getFoo', PropertyReadInfo::VISIBILITY_PUBLIC, false],
            [Php71Dummy::class, 'buz', true, PropertyReadInfo::TYPE_METHOD, 'getBuz', PropertyReadInfo::VISIBILITY_PUBLIC, false],
        ];
    }

    /**
     * @dataProvider writeMutatorProvider
     */
    public function testGetWriteMutator($class, $property, $allowConstruct, $found, $type, $name, $addName, $removeName, $visibility, $static)
    {
        $extractor = new ReflectionExtractor(null, null, null, true, ReflectionExtractor::ALLOW_PUBLIC | ReflectionExtractor::ALLOW_PROTECTED | ReflectionExtractor::ALLOW_PRIVATE);
        $writeMutator = $extractor->getWriteInfo($class, $property, [
            'enable_constructor_extraction' => $allowConstruct,
            'enable_getter_setter_extraction' => true,
        ]);

        if (!$found) {
            $this->assertEquals(PropertyWriteInfo::TYPE_NONE, $writeMutator->getType());

            return;
        }

        $this->assertNotNull($writeMutator);
        $this->assertSame($type, $writeMutator->getType());

        if (PropertyWriteInfo::TYPE_ADDER_AND_REMOVER === $writeMutator->getType()) {
            $this->assertNotNull($writeMutator->getAdderInfo());
            $this->assertSame($addName, $writeMutator->getAdderInfo()->getName());
            $this->assertNotNull($writeMutator->getRemoverInfo());
            $this->assertSame($removeName, $writeMutator->getRemoverInfo()->getName());
        }

        if (PropertyWriteInfo::TYPE_CONSTRUCTOR === $writeMutator->getType()) {
            $this->assertSame($name, $writeMutator->getName());
        }

        if (PropertyWriteInfo::TYPE_PROPERTY === $writeMutator->getType()) {
            $this->assertSame($name, $writeMutator->getName());
            $this->assertSame($visibility, $writeMutator->getVisibility());
            $this->assertSame($static, $writeMutator->isStatic());
        }

        if (PropertyWriteInfo::TYPE_METHOD === $writeMutator->getType()) {
            $this->assertSame($name, $writeMutator->getName());
            $this->assertSame($visibility, $writeMutator->getVisibility());
            $this->assertSame($static, $writeMutator->isStatic());
        }
    }

    public static function writeMutatorProvider(): array
    {
        return [
            [Dummy::class, 'bar', false, true, PropertyWriteInfo::TYPE_PROPERTY, 'bar', null, null, PropertyWriteInfo::VISIBILITY_PRIVATE, false],
            [Dummy::class, 'baz', false, true, PropertyWriteInfo::TYPE_PROPERTY, 'baz', null, null, PropertyWriteInfo::VISIBILITY_PROTECTED, false],
            [Dummy::class, 'bal', false, true, PropertyWriteInfo::TYPE_PROPERTY, 'bal', null, null, PropertyWriteInfo::VISIBILITY_PUBLIC, false],
            [Dummy::class, 'parent', false, true, PropertyWriteInfo::TYPE_PROPERTY, 'parent', null, null, PropertyWriteInfo::VISIBILITY_PUBLIC, false],
            [Dummy::class, 'staticSetter', false, true, PropertyWriteInfo::TYPE_METHOD, 'staticSetter', null, null, PropertyWriteInfo::VISIBILITY_PUBLIC, true],
            [Dummy::class, 'foo', false, true, PropertyWriteInfo::TYPE_PROPERTY, 'foo', null, null, PropertyWriteInfo::VISIBILITY_PUBLIC, false],
            [Php71Dummy::class, 'bar', false, true, PropertyWriteInfo::TYPE_METHOD, 'setBar', null, null, PropertyWriteInfo::VISIBILITY_PUBLIC, false],
            [Php71Dummy::class, 'string', false, false, '', '', null, null, PropertyWriteInfo::VISIBILITY_PUBLIC, false],
            [Php71Dummy::class, 'string', true, true,  PropertyWriteInfo::TYPE_CONSTRUCTOR, 'string', null, null, PropertyWriteInfo::VISIBILITY_PUBLIC, false],
            [Php71Dummy::class, 'baz', false, true, PropertyWriteInfo::TYPE_ADDER_AND_REMOVER, null, 'addBaz', 'removeBaz', PropertyWriteInfo::VISIBILITY_PUBLIC, false],
            [Php71DummyExtended::class, 'bar', false, true, PropertyWriteInfo::TYPE_METHOD, 'setBar', null, null, PropertyWriteInfo::VISIBILITY_PUBLIC, false],
            [Php71DummyExtended::class, 'string', false, false, -1, '', null, null, PropertyWriteInfo::VISIBILITY_PUBLIC, false],
            [Php71DummyExtended::class, 'string', true, true, PropertyWriteInfo::TYPE_CONSTRUCTOR, 'string', null, null, PropertyWriteInfo::VISIBILITY_PUBLIC, false],
            [Php71DummyExtended::class, 'baz', false, true, PropertyWriteInfo::TYPE_ADDER_AND_REMOVER, null, 'addBaz', 'removeBaz', PropertyWriteInfo::VISIBILITY_PUBLIC, false],
            [Php71DummyExtended2::class, 'bar', false, true, PropertyWriteInfo::TYPE_METHOD, 'setBar', null, null, PropertyWriteInfo::VISIBILITY_PUBLIC, false],
            [Php71DummyExtended2::class, 'string', false, false, '', '', null, null, PropertyWriteInfo::VISIBILITY_PUBLIC, false],
            [Php71DummyExtended2::class, 'string', true, false,  '', '', null, null, PropertyWriteInfo::VISIBILITY_PUBLIC, false],
            [Php71DummyExtended2::class, 'baz', false, true, PropertyWriteInfo::TYPE_ADDER_AND_REMOVER, null, 'addBaz', 'removeBaz', PropertyWriteInfo::VISIBILITY_PUBLIC, false],
        ];
    }

    public function testDisabledAdderAndRemoverReturnsError()
    {
        $writeMutator = $this->extractor->getWriteInfo(Php71Dummy::class, 'baz', [
            'enable_adder_remover_extraction' => false,
        ]);

        self::assertNotNull($writeMutator);
        self::assertSame(PropertyWriteInfo::TYPE_NONE, $writeMutator->getType());
        self::assertSame([\sprintf('The property "baz" in class "%s" can be defined with the methods "addBaz()", "removeBaz()" but the new value must be an array or an instance of \Traversable', Php71Dummy::class)], $writeMutator->getErrors());
    }

    public function testGetWriteInfoReadonlyProperties()
    {
        $writeMutatorConstructor = $this->extractor->getWriteInfo(Php81Dummy::class, 'foo', ['enable_constructor_extraction' => true]);
        $writeMutatorWithoutConstructor = $this->extractor->getWriteInfo(Php81Dummy::class, 'foo', ['enable_constructor_extraction' => false]);

        $this->assertSame(PropertyWriteInfo::TYPE_CONSTRUCTOR, $writeMutatorConstructor->getType());
        $this->assertSame(PropertyWriteInfo::TYPE_NONE, $writeMutatorWithoutConstructor->getType());
    }

    public function testAsymmetricVisibility()
    {
        $this->assertTrue($this->extractor->isReadable(AsymmetricVisibility::class, 'publicPrivate'));
        $this->assertTrue($this->extractor->isReadable(AsymmetricVisibility::class, 'publicProtected'));
        $this->assertFalse($this->extractor->isReadable(AsymmetricVisibility::class, 'protectedPrivate'));
        $this->assertFalse($this->extractor->isWritable(AsymmetricVisibility::class, 'publicPrivate'));
        $this->assertFalse($this->extractor->isWritable(AsymmetricVisibility::class, 'publicProtected'));
        $this->assertFalse($this->extractor->isWritable(AsymmetricVisibility::class, 'protectedPrivate'));
    }

    public function testAsymmetricVisibilityAllowPublicOnly()
    {
        $extractor = new ReflectionExtractor(null, null, null, true, ReflectionExtractor::ALLOW_PUBLIC);

        $this->assertTrue($extractor->isReadable(AsymmetricVisibility::class, 'publicPrivate'));
        $this->assertTrue($extractor->isReadable(AsymmetricVisibility::class, 'publicProtected'));
        $this->assertFalse($extractor->isReadable(AsymmetricVisibility::class, 'protectedPrivate'));
        $this->assertFalse($extractor->isWritable(AsymmetricVisibility::class, 'publicPrivate'));
        $this->assertFalse($extractor->isWritable(AsymmetricVisibility::class, 'publicProtected'));
        $this->assertFalse($extractor->isWritable(AsymmetricVisibility::class, 'protectedPrivate'));
    }

    public function testAsymmetricVisibilityAllowProtectedOnly()
    {
        $extractor = new ReflectionExtractor(null, null, null, true, ReflectionExtractor::ALLOW_PROTECTED);

        $this->assertFalse($extractor->isReadable(AsymmetricVisibility::class, 'publicPrivate'));
        $this->assertFalse($extractor->isReadable(AsymmetricVisibility::class, 'publicProtected'));
        $this->assertTrue($extractor->isReadable(AsymmetricVisibility::class, 'protectedPrivate'));
        $this->assertFalse($extractor->isWritable(AsymmetricVisibility::class, 'publicPrivate'));
        $this->assertTrue($extractor->isWritable(AsymmetricVisibility::class, 'publicProtected'));
        $this->assertFalse($extractor->isWritable(AsymmetricVisibility::class, 'protectedPrivate'));
    }

    public function testAsymmetricVisibilityAllowPrivateOnly()
    {
        $extractor = new ReflectionExtractor(null, null, null, true, ReflectionExtractor::ALLOW_PRIVATE);

        $this->assertFalse($extractor->isReadable(AsymmetricVisibility::class, 'publicPrivate'));
        $this->assertFalse($extractor->isReadable(AsymmetricVisibility::class, 'publicProtected'));
        $this->assertFalse($extractor->isReadable(AsymmetricVisibility::class, 'protectedPrivate'));
        $this->assertTrue($extractor->isWritable(AsymmetricVisibility::class, 'publicPrivate'));
        $this->assertFalse($extractor->isWritable(AsymmetricVisibility::class, 'publicProtected'));
        $this->assertTrue($extractor->isWritable(AsymmetricVisibility::class, 'protectedPrivate'));
    }

    public function testVirtualProperties()
    {
        $this->assertTrue($this->extractor->isReadable(VirtualProperties::class, 'virtualNoSetHook'));
        $this->assertTrue($this->extractor->isReadable(VirtualProperties::class, 'virtualSetHookOnly'));
        $this->assertTrue($this->extractor->isReadable(VirtualProperties::class, 'virtualHook'));
        $this->assertFalse($this->extractor->isWritable(VirtualProperties::class, 'virtualNoSetHook'));
        $this->assertTrue($this->extractor->isWritable(VirtualProperties::class, 'virtualSetHookOnly'));
        $this->assertTrue($this->extractor->isWritable(VirtualProperties::class, 'virtualHook'));
    }

    /**
     * @dataProvider provideAsymmetricVisibilityMutator
     */
    public function testAsymmetricVisibilityMutator(string $property, string $readVisibility, string $writeVisibility)
    {
        $extractor = new ReflectionExtractor(null, null, null, true, ReflectionExtractor::ALLOW_PUBLIC | ReflectionExtractor::ALLOW_PROTECTED | ReflectionExtractor::ALLOW_PRIVATE);
        $readMutator = $extractor->getReadInfo(AsymmetricVisibility::class, $property);
        $writeMutator = $extractor->getWriteInfo(AsymmetricVisibility::class, $property, [
            'enable_getter_setter_extraction' => true,
        ]);

        $this->assertSame(PropertyReadInfo::TYPE_PROPERTY, $readMutator->getType());
        $this->assertSame(PropertyWriteInfo::TYPE_PROPERTY, $writeMutator->getType());
        $this->assertSame($readVisibility, $readMutator->getVisibility());
        $this->assertSame($writeVisibility, $writeMutator->getVisibility());
    }

    public static function provideAsymmetricVisibilityMutator(): iterable
    {
        yield ['publicPrivate', PropertyReadInfo::VISIBILITY_PUBLIC, PropertyWriteInfo::VISIBILITY_PRIVATE];
        yield ['publicProtected', PropertyReadInfo::VISIBILITY_PUBLIC, PropertyWriteInfo::VISIBILITY_PROTECTED];
        yield ['protectedPrivate', PropertyReadInfo::VISIBILITY_PROTECTED, PropertyWriteInfo::VISIBILITY_PRIVATE];
    }

    /**
     * @dataProvider provideVirtualPropertiesMutator
     */
    public function testVirtualPropertiesMutator(string $property, string $readVisibility, string $writeVisibility)
    {
        $extractor = new ReflectionExtractor(null, null, null, true, ReflectionExtractor::ALLOW_PUBLIC | ReflectionExtractor::ALLOW_PROTECTED | ReflectionExtractor::ALLOW_PRIVATE);
        $readMutator = $extractor->getReadInfo(VirtualProperties::class, $property);
        $writeMutator = $extractor->getWriteInfo(VirtualProperties::class, $property, [
            'enable_getter_setter_extraction' => true,
        ]);

        $this->assertSame(PropertyReadInfo::TYPE_PROPERTY, $readMutator->getType());
        $this->assertSame(PropertyWriteInfo::TYPE_PROPERTY, $writeMutator->getType());
        $this->assertSame($readVisibility, $readMutator->getVisibility());
        $this->assertSame($writeVisibility, $writeMutator->getVisibility());
    }

    public static function provideVirtualPropertiesMutator(): iterable
    {
        yield ['virtualNoSetHook', PropertyReadInfo::VISIBILITY_PUBLIC, PropertyWriteInfo::VISIBILITY_PRIVATE];
        yield ['virtualSetHookOnly', PropertyReadInfo::VISIBILITY_PUBLIC, PropertyWriteInfo::VISIBILITY_PUBLIC];
        yield ['virtualHook', PropertyReadInfo::VISIBILITY_PUBLIC, PropertyWriteInfo::VISIBILITY_PUBLIC];
    }

    /**
     * @dataProvider typesProvider
     */
    public function testExtractors(string $property, ?Type $type)
    {
        $this->assertEquals($type, $this->extractor->getType(Dummy::class, $property));
    }

    /**
     * @return iterable<array{0: string, 1: ?Type}>
     */
    public static function typesProvider(): iterable
    {
        yield ['a', null];
        yield ['b', Type::nullable(Type::object(ParentDummy::class))];
        yield ['e', null];
        yield ['f', Type::list(Type::object(\DateTimeImmutable::class))];
        yield ['donotexist', null];
        yield ['staticGetter', null];
        yield ['staticSetter', null];
        yield ['self', Type::object(Dummy::class)];
        yield ['realParent', Type::object(ParentDummy::class)];
        yield ['date', Type::object(\DateTimeImmutable::class)];
        yield ['dates', Type::list(Type::object(\DateTimeImmutable::class))];
    }

    /**
     * @dataProvider php7TypesProvider
     */
    public function testExtractPhp7Type(string $class, string $property, ?Type $type)
    {
        $this->assertEquals($type, $this->extractor->getType($class, $property));
    }

    /**
     * @return iterable<array{0: string, 1: ?Type}>
     */
    public static function php7TypesProvider(): iterable
    {
        yield [Php7Dummy::class, 'foo', Type::array()];
        yield [Php7Dummy::class, 'bar', Type::int()];
        yield [Php7Dummy::class, 'baz', Type::list(Type::string())];
        yield [Php7Dummy::class, 'buz', Type::object(Php7Dummy::class)];
        yield [Php7Dummy::class, 'biz', Type::object(Php7ParentDummy::class)];
        yield [Php7Dummy::class, 'donotexist', null];
        yield [Php7ParentDummy::class, 'parent', Type::object(\stdClass::class)];
    }

    /**
     * @dataProvider php71TypesProvider
     */
    public function testExtractPhp71Type(string $property, ?Type $type)
    {
        $this->assertEquals($type, $this->extractor->getType(Php71Dummy::class, $property));
    }

    /**
     * @return iterable<array{0: string, 1: ?Type}>
     */
    public static function php71TypesProvider(): iterable
    {
        yield ['foo', Type::nullable(Type::array())];
        yield ['buz', Type::void()];
        yield ['bar', Type::nullable(Type::int())];
        yield ['baz', Type::list(Type::string())];
        yield ['donotexist', null];
    }

    /**
     * @dataProvider php80TypesProvider
     */
    public function testExtractPhp80Type(string $property, ?Type $type)
    {
        $this->assertEquals($type, $this->extractor->getType(Php80Dummy::class, $property));
    }

    /**
     * @return iterable<array{0: string, 1: ?Type}>
     */
    public static function php80TypesProvider(): iterable
    {
        yield ['foo', Type::nullable(Type::array())];
        yield ['bar', Type::nullable(Type::int())];
        yield ['timeout', Type::union(Type::int(), Type::float())];

        // BC layer for type-info < 7.2
        if (!class_exists(NullableType::class)) {
            yield ['optional', Type::union(Type::nullable(Type::int()), Type::nullable(Type::float()))];
        } else {
            yield ['optional', Type::nullable(Type::union(Type::float(), Type::int()))];
        }

        yield ['string', Type::union(Type::string(), Type::object(\Stringable::class))];
        yield ['payload', Type::mixed()];
        yield ['data', Type::mixed()];
        yield ['mixedProperty', Type::mixed()];
    }

    /**
     * @dataProvider php81TypesProvider
     */
    public function testExtractPhp81Type(string $property, ?Type $type)
    {
        $this->assertEquals($type, $this->extractor->getType(Php81Dummy::class, $property));
    }

    /**
     * @return iterable<array{0: string, 1: ?Type}>
     */
    public static function php81TypesProvider(): iterable
    {
        yield ['nothing', Type::never()];
        yield ['collection', Type::intersection(Type::object(\Traversable::class), Type::object(\Countable::class))];
    }

    /**
     * @dataProvider php82TypesProvider
     */
    public function testExtractPhp82Type(string $property, ?Type $type)
    {
        $this->assertEquals($type, $this->extractor->getType(Php82Dummy::class, $property));
    }

    /**
     * @return iterable<array{0: string, 1: ?Type}>
     */
    public static function php82TypesProvider(): iterable
    {
        yield ['nil', Type::null()];
        yield ['false', Type::false()];
        yield ['true', Type::true()];
        yield ['someCollection', Type::union(Type::intersection(Type::object(\Traversable::class), Type::object(\Countable::class)), Type::null())];
    }

    /**
     * @dataProvider defaultValueProvider
     */
    public function testExtractWithDefaultValue(string $property, ?Type $type)
    {
        $this->assertEquals($type, $this->extractor->getType(DefaultValue::class, $property));
    }

    /**
     * @return iterable<array{0: string, 1: ?Type}>
     */
    public static function defaultValueProvider(): iterable
    {
        yield ['defaultInt', Type::int()];
        yield ['defaultFloat', Type::float()];
        yield ['defaultString', Type::string()];
        yield ['defaultArray', Type::array()];
        yield ['defaultNull', null];
    }

    /**
     * @dataProvider constructorTypesProvider
     */
    public function testExtractTypeConstructor(string $class, string $property, ?Type $type)
    {
        /* Check that constructor extractions works by default, and if passed in via context.
           Check that null is returned if constructor extraction is disabled */
        $this->assertEquals($type, $this->extractor->getType($class, $property));
        $this->assertEquals($type, $this->extractor->getType($class, $property, ['enable_constructor_extraction' => true]));
        $this->assertNull($this->extractor->getType($class, $property, ['enable_constructor_extraction' => false]));
    }

    /**
     * @return iterable<array{0: class-string, 1: string, 1: ?Type}>
     */
    public static function constructorTypesProvider(): iterable
    {
        // php71 dummy has following constructor: __construct(string $string, int $intPrivate)
        yield [Php71Dummy::class, 'string', Type::string()];

        // Php71DummyExtended2 adds int $intWithAccessor
        yield [Php71DummyExtended2::class, 'intWithAccessor', Type::int()];

        yield [Php71Dummy::class, 'intPrivate', Type::int()];
        yield [Php71DummyExtended2::class, 'intPrivate', Type::int()];
        yield [DefaultValue::class, 'foo', null];
    }

    public function testTypedProperties()
    {
        $this->assertEquals(Type::object(Dummy::class), $this->extractor->getType(Php74Dummy::class, 'dummy'));
        $this->assertEquals(Type::nullable(Type::bool()), $this->extractor->getType(Php74Dummy::class, 'nullableBoolProp'));
        $this->assertEquals(Type::list(Type::string()), $this->extractor->getType(Php74Dummy::class, 'stringCollection'));
        $this->assertEquals(Type::nullable(Type::int()), $this->extractor->getType(Php74Dummy::class, 'nullableWithDefault'));
        $this->assertEquals(Type::array(), $this->extractor->getType(Php74Dummy::class, 'collection'));
        $this->assertEquals(Type::nullable(Type::list(Type::object(Dummy::class))), $this->extractor->getType(Php74Dummy::class, 'nullableTypedCollection'));
    }

    /**
     * @dataProvider extractConstructorTypesProvider
     */
    public function testExtractConstructorType(string $property, ?Type $type)
    {
        $this->assertEquals($type, $this->extractor->getTypeFromConstructor(ConstructorDummy::class, $property));
    }

    /**
     * @return iterable<array{0: string, 1: ?Type}>
     */
    public static function extractConstructorTypesProvider(): iterable
    {
        yield ['timezone', Type::object(\DateTimeZone::class)];
        yield ['date', null];
        yield ['dateObject', null];
        yield ['dateTime', Type::object(\DateTimeImmutable::class)];
        yield ['ddd', null];
    }
}
