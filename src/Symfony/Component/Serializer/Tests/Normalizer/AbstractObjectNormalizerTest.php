<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Normalizer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyDocBlockExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\DiscriminatorMap;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Serializer\Attribute\SerializedPath;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorMapping;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorResolverInterface;
use Symfony\Component\Serializer\Mapping\ClassMetadata;
use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\CustomNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\AbstractDummy;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\AbstractDummyFirstChild;
use Symfony\Component\Serializer\Tests\Fixtures\Attributes\AbstractDummySecondChild;
use Symfony\Component\Serializer\Tests\Fixtures\DummyFirstChildQuux;
use Symfony\Component\Serializer\Tests\Fixtures\DummyMessageInterface;
use Symfony\Component\Serializer\Tests\Fixtures\DummyMessageNumberFour;
use Symfony\Component\Serializer\Tests\Fixtures\DummySecondChildQuux;
use Symfony\Component\Serializer\Tests\Fixtures\DummyString;
use Symfony\Component\Serializer\Tests\Fixtures\DummyWithNotNormalizable;
use Symfony\Component\Serializer\Tests\Fixtures\DummyWithObjectOrBool;
use Symfony\Component\Serializer\Tests\Fixtures\DummyWithObjectOrNull;
use Symfony\Component\Serializer\Tests\Fixtures\DummyWithStringObject;
use Symfony\Component\Serializer\Tests\Normalizer\Features\ObjectDummyWithContextAttribute;
use Symfony\Component\TypeInfo\Type;

class AbstractObjectNormalizerTest extends TestCase
{
    public function testDenormalize()
    {
        $normalizer = new AbstractObjectNormalizerDummy();
        $normalizedData = $normalizer->denormalize(['foo' => 'foo', 'bar' => 'bar', 'baz' => 'baz'], Dummy::class);

        $this->assertSame('foo', $normalizedData->foo);
        $this->assertNull($normalizedData->bar);
        $this->assertSame('baz', $normalizedData->baz);
    }

    public function testInstantiateObjectDenormalizer()
    {
        $data = ['foo' => 'foo', 'bar' => 'bar', 'baz' => 'baz'];
        $class = Dummy::class;
        $context = [];

        $normalizer = new AbstractObjectNormalizerDummy();

        $this->assertInstanceOf(Dummy::class, $normalizer->instantiateObject($data, $class, $context, new \ReflectionClass($class), []));
    }

    public function testDenormalizeWithExtraAttribute()
    {
        $factory = new ClassMetadataFactory(new AttributeLoader());
        $normalizer = new AbstractObjectNormalizerDummy($factory);

        $this->expectException(ExtraAttributesException::class);
        $this->expectExceptionMessage('Extra attributes are not allowed ("fooFoo" is unknown).');

        $normalizer->denormalize(
            ['fooFoo' => 'foo'],
            Dummy::class,
            'any',
            ['allow_extra_attributes' => false]
        );
    }

    public function testDenormalizeWithExtraAttributes()
    {
        $factory = new ClassMetadataFactory(new AttributeLoader());
        $normalizer = new AbstractObjectNormalizerDummy($factory);

        $this->expectException(ExtraAttributesException::class);
        $this->expectExceptionMessage('Extra attributes are not allowed ("fooFoo", "fooBar" are unknown).');

        $normalizer->denormalize(
            ['fooFoo' => 'foo', 'fooBar' => 'bar'],
            Dummy::class,
            'any',
            ['allow_extra_attributes' => false]
        );
    }

    public function testDenormalizeWithExtraAttributesAndNoGroupsWithMetadataFactory()
    {
        $normalizer = new AbstractObjectNormalizerWithMetadata();

        $this->expectException(ExtraAttributesException::class);
        $this->expectExceptionMessage('Extra attributes are not allowed ("fooFoo", "fooBar" are unknown).');

        $normalizer->denormalize(
            ['fooFoo' => 'foo', 'fooBar' => 'bar', 'bar' => 'bar'],
            Dummy::class,
            'any',
            ['allow_extra_attributes' => false]
        );
    }

    public function testDenormalizePlainObject()
    {
        $extractor = new PhpDocExtractor();
        $normalizer = new ObjectNormalizer(null, null, null, $extractor);
        $dummy = $normalizer->denormalize(['plainObject' => (object) ['foo' => 'bar']], DummyWithPlainObject::class);

        $this->assertInstanceOf(DummyWithPlainObject::class, $dummy);
        $this->assertInstanceOf(\stdClass::class, $dummy->plainObject);
        $this->assertSame('bar', $dummy->plainObject->foo);
    }

    public function testDenormalizeWithDuplicateNestedAttributes()
    {
        $normalizer = new AbstractObjectNormalizerWithMetadata();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Duplicate serialized path: "one,two,three" used for properties "foo" and "bar".');

        $normalizer->denormalize([], DuplicateValueNestedDummy::class, 'any');
    }

    public function testDenormalizeWithNestedAttributesWithoutMetadata()
    {
        $normalizer = new AbstractObjectNormalizerDummy();
        $data = [
            'one' => [
                'two' => [
                    'three' => 'foo',
                ],
                'four' => 'quux',
            ],
            'foo' => 'notfoo',
            'baz' => 'baz',
        ];
        $test = $normalizer->denormalize($data, NestedDummy::class, 'any');
        $this->assertSame('notfoo', $test->foo);
        $this->assertSame('baz', $test->baz);
        $this->assertNull($test->notfoo);
    }

    public function testDenormalizeWithSnakeCaseNestedAttributes()
    {
        $factory = new ClassMetadataFactory(new AttributeLoader());
        $normalizer = new ObjectNormalizer($factory, new CamelCaseToSnakeCaseNameConverter());
        $data = [
            'one' => [
                'two_three' => 'fooBar',
            ],
        ];
        $test = $normalizer->denormalize($data, SnakeCaseNestedDummy::class, 'any');
        $this->assertSame('fooBar', $test->fooBar);
    }

    public function testNormalizeWithSnakeCaseNestedAttributes()
    {
        $factory = new ClassMetadataFactory(new AttributeLoader());
        $normalizer = new ObjectNormalizer($factory, new CamelCaseToSnakeCaseNameConverter());
        $dummy = new SnakeCaseNestedDummy();
        $dummy->fooBar = 'fooBar';
        $test = $normalizer->normalize($dummy, 'any');
        $this->assertSame(['one' => ['two_three' => 'fooBar']], $test);
    }

    public function testDenormalizeWithNestedAttributes()
    {
        $normalizer = new AbstractObjectNormalizerWithMetadata();
        $data = [
            'one' => [
                'two' => [
                    'three' => 'foo',
                ],
                'four' => 'quux',
            ],
            'foo' => 'notfoo',
            'baz' => 'baz',
        ];
        $test = $normalizer->denormalize($data, NestedDummy::class, 'any');
        $this->assertSame('baz', $test->baz);
        $this->assertSame('foo', $test->foo);
        $this->assertSame('quux', $test->quux);
        $this->assertSame('notfoo', $test->notfoo);
    }

    public function testDenormalizeWithNestedAttributesDuplicateKeys()
    {
        $normalizer = new AbstractObjectNormalizerWithMetadata();
        $data = [
            'one' => [
                'four' => 'quux',
            ],
            'quux' => 'notquux',
        ];

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Duplicate values for key "quux" found. One value is set via the SerializedPath attribute: "one->four", the other one is set via the SerializedName attribute: "notquux".');

        $normalizer->denormalize($data, DuplicateKeyNestedDummy::class, 'any');
    }

    public function testDenormalizeWithNestedAttributesInConstructor()
    {
        $normalizer = new AbstractObjectNormalizerWithMetadata();
        $data = [
            'one' => [
                'two' => [
                    'three' => 'foo',
                ],
                'four' => 'quux',
            ],
            'foo' => 'notfoo',
            'baz' => 'baz',
        ];
        $test = $normalizer->denormalize($data, NestedDummyWithConstructor::class, 'any');
        $this->assertSame('foo', $test->foo);
        $this->assertSame('quux', $test->quux);
        $this->assertSame('notfoo', $test->notfoo);
        $this->assertSame('baz', $test->baz);
    }

    public function testDenormalizeWithNestedAttributesInConstructorAndDiscriminatorMap()
    {
        $normalizer = new AbstractObjectNormalizerWithMetadata();
        $data = [
            'one' => [
                'two' => [
                    'three' => 'foo',
                ],
                'four' => 'quux',
            ],
            'foo' => 'notfoo',
            'baz' => 'baz',
        ];

        $test1 = $normalizer->denormalize($data + ['type' => 'first'], AbstractNestedDummyWithConstructorAndDiscriminator::class, 'any');
        $this->assertInstanceOf(FirstNestedDummyWithConstructorAndDiscriminator::class, $test1);
        $this->assertSame('foo', $test1->foo);
        $this->assertSame('notfoo', $test1->notfoo);
        $this->assertSame('baz', $test1->baz);

        $test2 = $normalizer->denormalize($data + ['type' => 'second'], AbstractNestedDummyWithConstructorAndDiscriminator::class, 'any');
        $this->assertInstanceOf(SecondNestedDummyWithConstructorAndDiscriminator::class, $test2);
        $this->assertSame('quux', $test2->quux);
        $this->assertSame('notfoo', $test2->notfoo);
        $this->assertSame('baz', $test2->baz);
    }

    public function testNormalizeWithNestedAttributesMixingArrayTypes()
    {
        $foobar = new AlreadyPopulatedNestedDummy();
        $foobar->foo = 'foo';
        $foobar->bar = 'bar';
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $normalizer = new ObjectNormalizer($classMetadataFactory, new MetadataAwareNameConverter($classMetadataFactory));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The element you are trying to set is already populated: "[one][two]"');

        $normalizer->normalize($foobar, 'any');
    }

    public function testNormalizeWithNestedAttributesElementAlreadySet()
    {
        $foobar = new DuplicateValueNestedDummy();
        $foobar->foo = 'foo';
        $foobar->bar = 'bar';
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $normalizer = new ObjectNormalizer($classMetadataFactory, new MetadataAwareNameConverter($classMetadataFactory));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The element you are trying to set is already populated: "[one][two][three]"');

        $normalizer->normalize($foobar, 'any');
    }

    public function testNormalizeWithNestedAttributes()
    {
        $foobar = new NestedDummy();
        $foobar->foo = 'foo';
        $foobar->quux = 'quux';
        $foobar->baz = 'baz';
        $foobar->notfoo = 'notfoo';
        $data = [
            'one' => [
                'two' => [
                    'three' => 'foo',
                ],
                'four' => 'quux',
            ],
            'foo' => 'notfoo',
            'baz' => 'baz',
        ];
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $normalizer = new ObjectNormalizer($classMetadataFactory, new MetadataAwareNameConverter($classMetadataFactory));
        $test = $normalizer->normalize($foobar, 'any');
        $this->assertSame($data, $test);
    }

    public function testNormalizeWithNestedAttributesWithoutMetadata()
    {
        $foobar = new NestedDummy();
        $foobar->foo = 'foo';
        $foobar->quux = 'quux';
        $foobar->baz = 'baz';
        $foobar->notfoo = 'notfoo';
        $data = [
            'foo' => 'foo',
            'quux' => 'quux',
            'notfoo' => 'notfoo',
            'baz' => 'baz',
        ];
        $normalizer = new ObjectNormalizer();
        $test = $normalizer->normalize($foobar, 'any');
        $this->assertSame($data, $test);
    }

    public function testNormalizeWithNestedAttributesInConstructor()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $normalizer = new ObjectNormalizer($classMetadataFactory, new MetadataAwareNameConverter($classMetadataFactory));

        $test = $normalizer->normalize(new NestedDummyWithConstructor('foo', 'quux', 'notfoo', 'baz'), 'any');
        $this->assertSame([
            'one' => [
                'two' => [
                    'three' => 'foo',
                ],
                'four' => 'quux',
            ],
            'foo' => 'notfoo',
            'baz' => 'baz',
        ], $test);
    }

    public function testNormalizeWithNestedAttributesInConstructorAndDiscriminatorMap()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $normalizer = new ObjectNormalizer($classMetadataFactory, new MetadataAwareNameConverter($classMetadataFactory));

        $test1 = $normalizer->normalize(new FirstNestedDummyWithConstructorAndDiscriminator('foo', 'notfoo', 'baz'), 'any');
        $this->assertSame([
            'type' => 'first',
            'one' => [
                'two' => [
                    'three' => 'foo',
                ],
            ],
            'foo' => 'notfoo',
            'baz' => 'baz',
        ], $test1);

        $test2 = $normalizer->normalize(new SecondNestedDummyWithConstructorAndDiscriminator('quux', 'notfoo', 'baz'), 'any');
        $this->assertSame([
            'type' => 'second',
            'one' => [
                'four' => 'quux',
            ],
            'foo' => 'notfoo',
            'baz' => 'baz',
        ], $test2);
    }

    public function testDenormalizeCollectionDecodedFromXmlWithOneChild()
    {
        $denormalizer = $this->getDenormalizerForDummyCollection();

        $dummyCollection = $denormalizer->denormalize(
            [
                'children' => [
                    'bar' => 'first',
                ],
            ],
            DummyCollection::class,
            'xml'
        );

        $this->assertInstanceOf(DummyCollection::class, $dummyCollection);
        $this->assertIsArray($dummyCollection->children);
        $this->assertCount(1, $dummyCollection->children);
        $this->assertInstanceOf(DummyChild::class, $dummyCollection->children[0]);
    }

    public function testDenormalizeCollectionDecodedFromXmlWithTwoChildren()
    {
        $denormalizer = $this->getDenormalizerForDummyCollection();

        $dummyCollection = $denormalizer->denormalize(
            [
                'children' => [
                    ['bar' => 'first'],
                    ['bar' => 'second'],
                ],
            ],
            DummyCollection::class,
            'xml'
        );

        $this->assertInstanceOf(DummyCollection::class, $dummyCollection);
        $this->assertIsArray($dummyCollection->children);
        $this->assertCount(2, $dummyCollection->children);
        $this->assertInstanceOf(DummyChild::class, $dummyCollection->children[0]);
        $this->assertInstanceOf(DummyChild::class, $dummyCollection->children[1]);
    }

    private function getDenormalizerForDummyCollection()
    {
        $extractor = $this->createMock(PhpDocExtractor::class);
        $extractor->method('getType')->willReturn(Type::list(Type::object(DummyChild::class)), null);

        $denormalizer = new AbstractObjectNormalizerCollectionDummy(null, null, $extractor);
        $arrayDenormalizer = new ArrayDenormalizerDummy();
        $serializer = new SerializerCollectionDummy([$arrayDenormalizer, $denormalizer]);
        $arrayDenormalizer->setSerializer($serializer);
        $denormalizer->setSerializer($serializer);

        return $denormalizer;
    }

    public function testDenormalizeStringCollectionDecodedFromXmlWithOneChild()
    {
        $denormalizer = $this->getDenormalizerForStringCollection();

        // if an xml-node can have children which should be deserialized as string[]
        // and only one child exists
        $stringCollection = $denormalizer->denormalize(['children' => 'foo'], StringCollection::class, 'xml');

        $this->assertInstanceOf(StringCollection::class, $stringCollection);
        $this->assertIsArray($stringCollection->children);
        $this->assertCount(1, $stringCollection->children);
        $this->assertEquals('foo', $stringCollection->children[0]);
    }

    public function testDenormalizeStringCollectionDecodedFromXmlWithTwoChildren()
    {
        $denormalizer = $this->getDenormalizerForStringCollection();

        // if an xml-node can have children which should be deserialized as string[]
        // and only one child exists
        $stringCollection = $denormalizer->denormalize(['children' => ['foo', 'bar']], StringCollection::class, 'xml');

        $this->assertInstanceOf(StringCollection::class, $stringCollection);
        $this->assertIsArray($stringCollection->children);
        $this->assertCount(2, $stringCollection->children);
        $this->assertEquals('foo', $stringCollection->children[0]);
        $this->assertEquals('bar', $stringCollection->children[1]);
    }

    public function testDenormalizeNotSerializableObjectToPopulate()
    {
        $normalizer = new AbstractObjectNormalizerDummy();
        $normalizedData = $normalizer->denormalize(['foo' => 'foo'], Dummy::class, null, [AbstractObjectNormalizer::OBJECT_TO_POPULATE => new NotSerializable()]);

        $this->assertSame('foo', $normalizedData->foo);
    }

    private function getDenormalizerForStringCollection()
    {
        $extractor = $this->createMock(PhpDocExtractor::class);
        $extractor->method('getType')->willReturn(Type::list(Type::string()), null);

        $denormalizer = new AbstractObjectNormalizerCollectionDummy(null, null, $extractor);
        $arrayDenormalizer = new ArrayDenormalizerDummy();
        $serializer = new SerializerCollectionDummy([$arrayDenormalizer, $denormalizer]);
        $arrayDenormalizer->setSerializer($serializer);
        $denormalizer->setSerializer($serializer);

        return $denormalizer;
    }

    public function testDenormalizeWithDiscriminatorMapUsesCorrectClassname()
    {
        $factory = new ClassMetadataFactory(new AttributeLoader());

        $loaderMock = new class implements ClassMetadataFactoryInterface {
            public function getMetadataFor($value): ClassMetadataInterface
            {
                if (AbstractDummy::class === $value) {
                    return new ClassMetadata(
                        AbstractDummy::class,
                        new ClassDiscriminatorMapping('type', [
                            'first' => AbstractDummyFirstChild::class,
                            'second' => AbstractDummySecondChild::class,
                        ])
                    );
                }

                throw new InvalidArgumentException();
            }

            public function hasMetadataFor($value): bool
            {
                return AbstractDummy::class === $value;
            }
        };

        $discriminatorResolver = new ClassDiscriminatorFromClassMetadata($loaderMock);
        $normalizer = new AbstractObjectNormalizerDummy($factory, null, new PhpDocExtractor(), $discriminatorResolver);
        $serializer = new Serializer([$normalizer]);
        $normalizer->setSerializer($serializer);
        $normalizedData = $normalizer->denormalize(['foo' => 'foo', 'baz' => 'baz', 'quux' => ['value' => 'quux'], 'type' => 'second'], AbstractDummy::class);

        $this->assertInstanceOf(DummySecondChildQuux::class, $normalizedData->quux);
    }

    public function testDenormalizeWithDiscriminatorMapUsesCorrectClassnameWithDefaultType()
    {
        $factory = new ClassMetadataFactory(new AttributeLoader());

        $loaderMock = new class implements ClassMetadataFactoryInterface {
            public function getMetadataFor($value): ClassMetadataInterface
            {
                if (AbstractDummy::class === $value) {
                    return new ClassMetadata(
                        AbstractDummy::class,
                        new ClassDiscriminatorMapping('type', [
                            'first' => AbstractDummyFirstChild::class,
                            'second' => AbstractDummySecondChild::class,
                        ], 'second')
                    );
                }

                throw new InvalidArgumentException(\sprintf('"%s" is not handled.', $value));
            }

            public function hasMetadataFor($value): bool
            {
                return AbstractDummy::class === $value;
            }
        };

        $discriminatorResolver = new ClassDiscriminatorFromClassMetadata($loaderMock);
        $normalizer = new AbstractObjectNormalizerDummy($factory, null, new PhpDocExtractor(), $discriminatorResolver);
        $serializer = new Serializer([$normalizer]);
        $normalizer->setSerializer($serializer);
        $normalizedData = $normalizer->denormalize(['foo' => 'foo', 'baz' => 'baz', 'quux' => ['value' => 'quux']], AbstractDummy::class);

        $this->assertInstanceOf(DummySecondChildQuux::class, $normalizedData->quux);
    }

    public function testDenormalizeWithDiscriminatorMapAndObjectToPopulateUsesCorrectClassname()
    {
        $factory = new ClassMetadataFactory(new AttributeLoader());

        $loaderMock = new class implements ClassMetadataFactoryInterface {
            public function getMetadataFor($value): ClassMetadataInterface
            {
                if (AbstractDummy::class === $value) {
                    return new ClassMetadata(
                        AbstractDummy::class,
                        new ClassDiscriminatorMapping('type', [
                            'first' => AbstractDummyFirstChild::class,
                            'second' => AbstractDummySecondChild::class,
                        ])
                    );
                }

                throw new InvalidArgumentException();
            }

            public function hasMetadataFor($value): bool
            {
                return AbstractDummy::class === $value;
            }
        };

        $discriminatorResolver = new ClassDiscriminatorFromClassMetadata($loaderMock);
        $normalizer = new AbstractObjectNormalizerDummy($factory, null, new PhpDocExtractor(), $discriminatorResolver);
        $serializer = new Serializer([$normalizer]);
        $normalizer->setSerializer($serializer);

        $data = [
            'foo' => 'foo',
            'quux' => ['value' => 'quux'],
        ];

        $normalizedData1 = $normalizer->denormalize($data + ['bar' => 'bar'], AbstractDummy::class, 'any', [
            AbstractNormalizer::OBJECT_TO_POPULATE => new AbstractDummyFirstChild('notfoo', 'notbar'),
        ]);
        $this->assertInstanceOf(AbstractDummyFirstChild::class, $normalizedData1);
        $this->assertSame('foo', $normalizedData1->foo);
        $this->assertSame('notbar', $normalizedData1->bar);
        $this->assertInstanceOf(DummyFirstChildQuux::class, $normalizedData1->quux);
        $this->assertSame('quux', $normalizedData1->quux->getValue());

        $normalizedData2 = $normalizer->denormalize($data + ['baz' => 'baz'], AbstractDummy::class, 'any', [
            AbstractNormalizer::OBJECT_TO_POPULATE => new AbstractDummySecondChild('notfoo', 'notbaz'),
        ]);
        $this->assertInstanceOf(AbstractDummySecondChild::class, $normalizedData2);
        $this->assertSame('foo', $normalizedData2->foo);
        $this->assertSame('baz', $normalizedData2->baz);
        $this->assertInstanceOf(DummySecondChildQuux::class, $normalizedData2->quux);
        $this->assertSame('quux', $normalizedData2->quux->getValue());
    }

    public function testDenormalizeWithNestedDiscriminatorMap()
    {
        $classDiscriminatorResolver = new class implements ClassDiscriminatorResolverInterface {
            public function getMappingForClass(string $class): ?ClassDiscriminatorMapping
            {
                return match ($class) {
                    AbstractDummy::class => new ClassDiscriminatorMapping('type', [
                        'foo' => AbstractDummyFirstChild::class,
                    ]),
                    AbstractDummyFirstChild::class => new ClassDiscriminatorMapping('nested_type', [
                        'bar' => AbstractDummySecondChild::class,
                    ]),
                    default => null,
                };
            }

            public function getMappingForMappedObject($object): ?ClassDiscriminatorMapping
            {
                return null;
            }

            public function getTypeForMappedObject($object): ?string
            {
                return null;
            }
        };

        $normalizer = new AbstractObjectNormalizerDummy(null, null, null, $classDiscriminatorResolver);

        $denormalizedData = $normalizer->denormalize(['type' => 'foo', 'nested_type' => 'bar'], AbstractDummy::class);

        $this->assertInstanceOf(AbstractDummySecondChild::class, $denormalizedData);
    }

    public function testDenormalizeBasicTypePropertiesFromXml()
    {
        $denormalizer = $this->getDenormalizerForObjectWithBasicProperties();

        // bool
        $objectWithBooleanProperties = $denormalizer->denormalize(
            [
                'boolTrue1' => 'true',
                'boolFalse1' => 'false',
                'boolTrue2' => '1',
                'boolFalse2' => '0',
                'int1' => '4711',
                'int2' => '-4711',
                'float1' => '123.456',
                'float2' => '-1.2344e56',
                'float3' => '45E-6',
                'floatNaN' => 'NaN',
                'floatInf' => 'INF',
                'floatNegInf' => '-INF',
            ],
            ObjectWithBasicProperties::class,
            'xml'
        );

        $this->assertInstanceOf(ObjectWithBasicProperties::class, $objectWithBooleanProperties);

        // Bool Properties
        $this->assertTrue($objectWithBooleanProperties->boolTrue1);
        $this->assertFalse($objectWithBooleanProperties->boolFalse1);
        $this->assertTrue($objectWithBooleanProperties->boolTrue2);
        $this->assertFalse($objectWithBooleanProperties->boolFalse2);

        // Integer Properties
        $this->assertEquals(4711, $objectWithBooleanProperties->int1);
        $this->assertEquals(-4711, $objectWithBooleanProperties->int2);

        // Float Properties
        $this->assertEqualsWithDelta(123.456, $objectWithBooleanProperties->float1, 0.01);
        $this->assertEqualsWithDelta(-1.2344e56, $objectWithBooleanProperties->float2, 1);
        $this->assertEqualsWithDelta(45E-6, $objectWithBooleanProperties->float3, 1);
        $this->assertNan($objectWithBooleanProperties->floatNaN);
        $this->assertInfinite($objectWithBooleanProperties->floatInf);
        $this->assertEquals(-\INF, $objectWithBooleanProperties->floatNegInf);
    }

    private function getDenormalizerForObjectWithBasicProperties()
    {
        $extractor = $this->createMock(PhpDocExtractor::class);
        $extractor->method('getType')
            ->willReturn(
                Type::bool(),
                Type::bool(),
                Type::bool(),
                Type::bool(),
                Type::int(),
                Type::int(),
                Type::float(),
                Type::float(),
                Type::float(),
                Type::float(),
                Type::float(),
                Type::float(),
            );

        $denormalizer = new AbstractObjectNormalizerCollectionDummy(null, null, $extractor);
        $arrayDenormalizer = new ArrayDenormalizerDummy();
        $serializer = new SerializerCollectionDummy([$arrayDenormalizer, $denormalizer]);
        $arrayDenormalizer->setSerializer($serializer);
        $denormalizer->setSerializer($serializer);

        return $denormalizer;
    }

    /**
     * Test that additional attributes throw an exception if no metadata factory is specified.
     */
    public function testExtraAttributesException()
    {
        $normalizer = new ObjectNormalizer();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A class metadata factory must be provided in the constructor when setting "allow_extra_attributes" to false.');

        $normalizer->denormalize([], \stdClass::class, 'xml', [
            'allow_extra_attributes' => false,
        ]);
    }

    public function testNormalizeEmptyObject()
    {
        $normalizer = new AbstractObjectNormalizerDummy();

        // This results in objects turning into arrays in some encoders
        $normalizedData = $normalizer->normalize(new EmptyDummy());
        $this->assertEquals([], $normalizedData);

        $normalizedData = $normalizer->normalize(new EmptyDummy(), 'any', ['preserve_empty_objects' => true]);
        $this->assertEquals(new \ArrayObject(), $normalizedData);
    }

    public function testDenormalizeRecursiveWithObjectAttributeWithStringValue()
    {
        $extractor = new ReflectionExtractor();
        $normalizer = new ObjectNormalizer(null, null, null, $extractor);
        $serializer = new Serializer([$normalizer]);

        $obj = $serializer->denormalize(['inner' => 'foo'], ObjectOuter::class);

        $this->assertInstanceOf(ObjectInner::class, $obj->getInner());
    }

    public function testDenormalizeUsesContextAttributeForPropertiesInConstructorWithSeralizedName()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());

        $extractor = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);
        $normalizer = new ObjectNormalizer($classMetadataFactory, new MetadataAwareNameConverter($classMetadataFactory), null, $extractor);
        $serializer = new Serializer([new DateTimeNormalizer([DateTimeNormalizer::FORMAT_KEY => 'd-m-Y']), $normalizer]);

        /** @var ObjectDummyWithContextAttribute $obj */
        $obj = $serializer->denormalize(['property_with_serialized_name' => '01-02-2022', 'propertyWithoutSerializedName' => '01-02-2022'], ObjectDummyWithContextAttribute::class);

        $this->assertSame($obj->propertyWithSerializedName->format('Y-m-d'), $obj->propertyWithoutSerializedName->format('Y-m-d'));
    }

    public function testNormalizeUsesContextAttributeForPropertiesInConstructorWithSerializedPath()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());

        $extractor = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);
        $normalizer = new ObjectNormalizer($classMetadataFactory, new MetadataAwareNameConverter($classMetadataFactory), null, $extractor);
        $serializer = new Serializer([new DateTimeNormalizer(), $normalizer]);

        $obj = new ObjectDummyWithContextAttributeAndSerializedPath(new \DateTimeImmutable('22-02-2023'));

        $data = $serializer->normalize($obj);

        $this->assertSame(['property' => ['with_path' => '02-22-2023']], $data);
    }

    public function testNormalizeUsesContextAttributeForProperties()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());

        $extractor = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);
        $normalizer = new ObjectNormalizer($classMetadataFactory, new MetadataAwareNameConverter($classMetadataFactory), null, $extractor);
        $serializer = new Serializer([$normalizer]);

        $obj = new ObjectDummyWithContextAttributeSkipNullValues();

        $data = $serializer->normalize($obj);

        $this->assertSame(['propertyWithoutNullSkipNullValues' => 'foo'], $data);
    }

    public function testDefaultExcludeFromCacheKey()
    {
        $object = new DummyChild();
        $object->bar = 'not called';

        $normalizer = new class(null, null, null, null, null, [AbstractObjectNormalizer::EXCLUDE_FROM_CACHE_KEY => ['foo']]) extends AbstractObjectNormalizerDummy {
            public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
            {
                AbstractObjectNormalizerTest::assertContains('foo', $this->defaultContext[ObjectNormalizer::EXCLUDE_FROM_CACHE_KEY]);
                $data->bar = 'called';

                return true;
            }
        };

        $serializer = new Serializer([$normalizer]);
        $serializer->normalize($object);

        $this->assertSame('called', $object->bar);
    }

    public function testDenormalizeUnionOfEnums()
    {
        $serializer = new Serializer([
            new BackedEnumNormalizer(),
            new ObjectNormalizer(
                classMetadataFactory: new ClassMetadataFactory(new AttributeLoader()),
                propertyTypeExtractor: new PropertyInfoExtractor([], [new ReflectionExtractor()]),
            ),
        ]);

        $normalized = $serializer->normalize(new DummyWithEnumUnion(EnumA::A));
        $this->assertEquals(new DummyWithEnumUnion(EnumA::A), $serializer->denormalize($normalized, DummyWithEnumUnion::class));

        $normalized = $serializer->normalize(new DummyWithEnumUnion(EnumB::B));
        $this->assertEquals(new DummyWithEnumUnion(EnumB::B), $serializer->denormalize($normalized, DummyWithEnumUnion::class));
    }

    public function testDenormalizeWithNumberAsSerializedNameAndNoArrayReindex()
    {
        $normalizer = new AbstractObjectNormalizerWithMetadata();

        $data = [
            '1' => 'foo',
            '99' => 'baz',
        ];

        $obj = new class {
            #[SerializedName('1')]
            public $foo;

            #[SerializedName('99')]
            public $baz;
        };

        $test = $normalizer->denormalize($data, $obj::class);
        $this->assertSame('foo', $test->foo);
        $this->assertSame('baz', $test->baz);
    }

    public function testDenormalizeWithCorrectOrderOfAttributeAndProperty()
    {
        $normalizer = new AbstractObjectNormalizerWithMetadata();

        $data = [
            'id' => 'root-level-id',
            'data' => [
                'id' => 'nested-id',
            ],
        ];

        $obj = new class {
            #[SerializedPath('[data][id]')]
            public $id;
        };

        $test = $normalizer->denormalize($data, $obj::class);
        $this->assertSame('nested-id', $test->id);
    }

    public function testNormalizeBasedOnAllowedAttributes()
    {
        $normalizer = new class extends AbstractObjectNormalizer {
            public function getSupportedTypes(?string $format): array
            {
                return ['*' => false];
            }

            protected function getAllowedAttributes($classOrObject, array $context, bool $attributesAsString = false): array
            {
                return ['foo'];
            }

            protected function extractAttributes(object $object, ?string $format = null, array $context = []): array
            {
                return [];
            }

            protected function getAttributeValue(object $object, string $attribute, ?string $format = null, array $context = []): mixed
            {
                return $object->$attribute;
            }

            protected function setAttributeValue(object $object, string $attribute, $value, ?string $format = null, array $context = []): void
            {
            }
        };

        $object = new Dummy();
        $object->foo = 'foo';
        $object->bar = 'bar';

        $this->assertSame(['foo' => 'foo'], $normalizer->normalize($object));
    }

    public function testDenormalizeUntypedFormat()
    {
        $serializer = new Serializer([new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]))]);
        $actual = $serializer->denormalize(['value' => ''], DummyWithObjectOrNull::class, 'xml');

        $this->assertEquals(new DummyWithObjectOrNull(null), $actual);
    }

    public function testDenormalizeUntypedFormatNotNormalizable()
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('Custom exception message');
        $serializer = new Serializer([new CustomNormalizer(), new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]))]);
        $serializer->denormalize(['value' => 'test'], DummyWithNotNormalizable::class, 'xml');
    }

    public function testDenormalizeUntypedFormatMissingArg()
    {
        $this->expectException(MissingConstructorArgumentsException::class);
        $serializer = new Serializer([new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]))]);
        $serializer->denormalize(['value' => 'invalid'], DummyWithObjectOrNull::class, 'xml');
    }

    public function testDenormalizeUntypedFormatScalar()
    {
        $serializer = new Serializer([new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]))]);
        $actual = $serializer->denormalize(['value' => 'false'], DummyWithObjectOrBool::class, 'xml');

        $this->assertEquals(new DummyWithObjectOrBool(false), $actual);
    }

    public function testDenormalizeUntypedStringObject()
    {
        $serializer = new Serializer([new CustomNormalizer(), new ObjectNormalizer(null, null, null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]))]);
        $actual = $serializer->denormalize(['value' => ''], DummyWithStringObject::class, 'xml');

        $this->assertEquals(new DummyWithStringObject(new DummyString()), $actual);
        $this->assertEquals('', $actual->value->value);
    }

    public function testProvidingContextCacheKeyGeneratesSameChildContextCacheKey()
    {
        $foobar = new Dummy();
        $foobar->foo = new EmptyDummy();
        $foobar->bar = 'bar';
        $foobar->baz = 'baz';

        $normalizer = new class extends AbstractObjectNormalizerDummy {
            public $childContextCacheKey;

            protected function extractAttributes(object $object, ?string $format = null, array $context = []): array
            {
                return array_keys((array) $object);
            }

            protected function getAttributeValue(object $object, string $attribute, ?string $format = null, array $context = []): mixed
            {
                return $object->{$attribute};
            }

            protected function createChildContext(array $parentContext, string $attribute, ?string $format): array
            {
                $childContext = parent::createChildContext($parentContext, $attribute, $format);
                $this->childContextCacheKey = $childContext['cache_key'];

                return $childContext;
            }
        };

        $serializer = new Serializer([$normalizer]);

        $serializer->normalize($foobar, null, ['cache_key' => 'hardcoded', 'iri' => '/dummy/1']);
        $firstChildContextCacheKey = $normalizer->childContextCacheKey;

        $serializer->normalize($foobar, null, ['cache_key' => 'hardcoded', 'iri' => '/dummy/2']);
        $secondChildContextCacheKey = $normalizer->childContextCacheKey;

        $this->assertSame($firstChildContextCacheKey, $secondChildContextCacheKey);
    }

    public function testChildContextKeepsOriginalContextCacheKey()
    {
        $foobar = new Dummy();
        $foobar->foo = new EmptyDummy();
        $foobar->bar = 'bar';
        $foobar->baz = 'baz';

        $normalizer = new class extends AbstractObjectNormalizerDummy {
            public $childContextCacheKey;

            protected function extractAttributes(object $object, ?string $format = null, array $context = []): array
            {
                return array_keys((array) $object);
            }

            protected function getAttributeValue(object $object, string $attribute, ?string $format = null, array $context = []): mixed
            {
                return $object->{$attribute};
            }

            protected function createChildContext(array $parentContext, string $attribute, ?string $format): array
            {
                $childContext = parent::createChildContext($parentContext, $attribute, $format);
                $this->childContextCacheKey = $childContext['cache_key'];

                return $childContext;
            }
        };

        $serializer = new Serializer([$normalizer]);
        $serializer->normalize($foobar, null, ['cache_key' => 'hardcoded', 'iri' => '/dummy/1']);

        $this->assertSame('hardcoded-foo', $normalizer->childContextCacheKey);
    }

    public function testChildContextCacheKeyStaysFalseWhenOriginalCacheKeyIsFalse()
    {
        $foobar = new Dummy();
        $foobar->foo = new EmptyDummy();
        $foobar->bar = 'bar';
        $foobar->baz = 'baz';

        $normalizer = new class extends AbstractObjectNormalizerDummy {
            public $childContextCacheKey;

            protected function extractAttributes(object $object, ?string $format = null, array $context = []): array
            {
                return array_keys((array) $object);
            }

            protected function getAttributeValue(object $object, string $attribute, ?string $format = null, array $context = []): mixed
            {
                return $object->{$attribute};
            }

            protected function createChildContext(array $parentContext, string $attribute, ?string $format): array
            {
                $childContext = parent::createChildContext($parentContext, $attribute, $format);
                $this->childContextCacheKey = $childContext['cache_key'];

                return $childContext;
            }
        };

        $serializer = new Serializer([$normalizer]);
        $serializer->normalize($foobar, null, ['cache_key' => false]);

        $this->assertFalse($normalizer->childContextCacheKey);
    }

    public function testDenormalizeXmlScalar()
    {
        $normalizer = new class extends AbstractObjectNormalizer {
            public function __construct()
            {
                parent::__construct(null, new MetadataAwareNameConverter(new ClassMetadataFactory(new AttributeLoader())));
            }

            protected function extractAttributes(object $object, ?string $format = null, array $context = []): array
            {
                return [];
            }

            protected function getAttributeValue(object $object, string $attribute, ?string $format = null, array $context = []): mixed
            {
                return null;
            }

            protected function setAttributeValue(object $object, string $attribute, $value, ?string $format = null, array $context = []): void
            {
                $object->$attribute = $value;
            }

            public function getSupportedTypes(?string $format): array
            {
                return ['*' => false];
            }
        };

        $this->assertSame('scalar', $normalizer->denormalize('scalar', XmlScalarDummy::class, 'xml')->value);
    }

    public function testNormalizationWithMaxDepthOnStdclassObjectDoesNotThrowWarning()
    {
        $object = new \stdClass();
        $object->string = 'yes';

        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $normalizer = new ObjectNormalizer($classMetadataFactory);
        $normalized = $normalizer->normalize($object, context: [
            AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true,
        ]);

        $this->assertSame(['string' => 'yes'], $normalized);
    }

    public function testDenormalizeCollectionOfScalarTypesPropertyWithPhpDocExtractor()
    {
        $normalizer = new AbstractObjectNormalizerWithMetadataAndPropertyTypeExtractors();
        $data = [
            'type' => 'foo',
            'values' => [
                ['1'],
                ['2'],
                ['3'],
                ['4'],
                ['5'],
            ],
        ];
        $expected = new ScalarCollectionDocBlockDummy([[1], [2], [3], [4], [5]]);

        $this->assertEquals($expected, $normalizer->denormalize($data, ScalarCollectionDocBlockDummy::class));
    }

    public function testDenormalizeCollectionOfUnionTypesPropertyWithPhpDocExtractor()
    {
        $normalizer = new AbstractObjectNormalizerWithMetadataAndPropertyTypeExtractors();
        $data = [
            'values1' => [
                'foo' => 'foo',
                'bar' => 222,
            ],
            'values2' => [
                'baz' => 'baz',
                'qux' => 333,
            ],
        ];
        $expected = new UnionCollectionDocBlockDummy($data['values1']);
        $expected->values2 = $data['values2'];

        $this->assertEquals($expected, $normalizer->denormalize($data, UnionCollectionDocBlockDummy::class));
    }

    public function testDenormalizeMixedProperty()
    {
        $normalizer = new AbstractObjectNormalizerWithMetadataAndPropertyTypeExtractors();
        $expected = new MixedPropertyDummy();
        $expected->foo = 'bar';

        $this->assertEquals($expected, $normalizer->denormalize(['foo' => 'bar'], MixedPropertyDummy::class));
    }

    /**
     * @dataProvider provideBooleanTypesData
     */
    public function testDenormalizeBooleanTypesWithNotMatchingData(array $data, string $type)
    {
        $normalizer = new AbstractObjectNormalizerWithMetadataAndPropertyTypeExtractors();

        $this->expectException(NotNormalizableValueException::class);

        $normalizer->denormalize($data, $type);
    }

    public static function provideBooleanTypesData()
    {
        return [
            [['foo' => true], FalsePropertyDummy::class],
            [['foo' => false], TruePropertyDummy::class],
        ];
    }

    public function testDeserializeAndSerializeConstructorAndIgnoreAndInterfacedObjectsWithTheClassMetadataDiscriminator()
    {
        $example = new DummyMessageNumberFour('Hello');

        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());

        $normalizer = new PropertyNormalizer(
            $classMetadataFactory,
            null,
            new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]),
            new ClassDiscriminatorFromClassMetadata($classMetadataFactory),
        );

        $serialized = $normalizer->normalize($example, 'json');
        $deserialized = $normalizer->denormalize($serialized, DummyMessageInterface::class, 'json');

        $this->assertEquals($example, $deserialized);
    }

    /**
     * @dataProvider provideDenormalizeWithFilterBoolData
     */
    public function testDenormalizeBooleanTypeWithFilterBool(array $data, ?bool $expectedFoo)
    {
        $normalizer = new AbstractObjectNormalizerWithMetadataAndPropertyTypeExtractors();

        $dummy = $normalizer->denormalize($data, BoolPropertyDummy::class, null, [AbstractNormalizer::FILTER_BOOL => true]);

        $this->assertSame($expectedFoo, $dummy->foo);
    }

    public static function provideDenormalizeWithFilterBoolData(): array
    {
        return [
            [['foo' => 'true'], true],
            [['foo' => 'True'], true],
            [['foo' => 'TRUE'], true],
            [['foo' => '1'], true],
            [['foo' => 1], true],
            [['foo' => 'yes'], true],
            [['foo' => 'Yes'], true],
            [['foo' => 'YES'], true],
            [['foo' => 'on'], true],
            [['foo' => 'On'], true],
            [['foo' => 'ON'], true],
            [['foo' => 'false'], false],
            [['foo' => 'False'], false],
            [['foo' => 'FALSE'], false],
            [['foo' => '0'], false],
            [['foo' => 0], false],
            [['foo' => 'no'], false],
            [['foo' => 'No'], false],
            [['foo' => 'NO'], false],
            [['foo' => 'off'], false],
            [['foo' => 'Off'], false],
            [['foo' => 'OFF'], false],
            [['foo' => ''], false],
            [['foo' => null], null],
            [['foo' => 'null'], null],
            [['foo' => 'something'], null],
            [['foo' => 'foo'], null],
            [['foo' => 1234567890], null],
            [['foo' => -1234567890], null],
        ];
    }

    public function testDenormalizeArrayObject()
    {
        $normalizer = new class extends AbstractObjectNormalizerDummy {
            public function __construct()
            {
                parent::__construct(null, null, new PhpDocExtractor());
            }

            protected function isAllowedAttribute($classOrObject, string $attribute, ?string $format = null, array $context = []): bool
            {
                return true;
            }
        };
        $serializer = new Serializer([$normalizer]);
        $normalizer->setSerializer($serializer);

        $actual = $normalizer->denormalize(['foo' => ['array' => ['key' => 'value']]], DummyWithArrayObject::class);

        $this->assertInstanceOf(DummyWithArrayObject::class, $actual);
        $this->assertInstanceOf(\ArrayObject::class, $actual->foo);
        $this->assertSame(1, $actual->foo->count());
    }

    public function testTemplateTypeWhenAnObjectIsPassedToDenormalize()
    {
        $normalizer = new class(classMetadataFactory: new ClassMetadataFactory(new AttributeLoader()), propertyTypeExtractor: new PropertyInfoExtractor(typeExtractors: [new PhpStanExtractor(), new ReflectionExtractor()])) extends AbstractObjectNormalizerDummy {
            protected function isAllowedAttribute($classOrObject, string $attribute, ?string $format = null, array $context = []): bool
            {
                return true;
            }
        };
        $serializer = new Serializer([$normalizer]);
        $normalizer->setSerializer($serializer);

        $denormalizedData = $normalizer->denormalize(['value' => new DummyGenericsValue()], DummyGenericsValueWrapper::class);

        $this->assertInstanceOf(DummyGenericsValueWrapper::class, $denormalizedData);
        $this->assertInstanceOf(DummyGenericsValue::class, $denormalizedData->value);

        $this->assertSame('dummy', $denormalizedData->value->type);
    }

    public function testDenormalizeTemplateType()
    {
        if (!interface_exists(PropertyDocBlockExtractorInterface::class)) {
            $this->markTestSkipped('The PropertyInfo component before Symfony 7.1 does not support template types.');
        }

        $normalizer = new class(classMetadataFactory: new ClassMetadataFactory(new AttributeLoader()), propertyTypeExtractor: new PropertyInfoExtractor(typeExtractors: [new PhpStanExtractor(), new ReflectionExtractor()])) extends AbstractObjectNormalizerDummy {
            protected function isAllowedAttribute($classOrObject, string $attribute, ?string $format = null, array $context = []): bool
            {
                return true;
            }
        };
        $serializer = new Serializer([new ArrayDenormalizer(), $normalizer]);
        $normalizer->setSerializer($serializer);

        $denormalizedData = $normalizer->denormalize(['value' => ['type' => 'dummy'], 'values' => [['type' => 'dummy']]], DummyGenericsValueWrapper::class);

        $this->assertInstanceOf(DummyGenericsValueWrapper::class, $denormalizedData);
        $this->assertInstanceOf(DummyGenericsValue::class, $denormalizedData->value);
        $this->assertContainsOnlyInstancesOf(DummyGenericsValue::class, $denormalizedData->values);
        $this->assertCount(1, $denormalizedData->values);
        $this->assertSame('dummy', $denormalizedData->value->type);
        $this->assertSame('dummy', $denormalizedData->values[0]->type);
    }
}

class AbstractObjectNormalizerDummy extends AbstractObjectNormalizer
{
    public function getSupportedTypes(?string $format): array
    {
        return ['*' => false];
    }

    protected function extractAttributes(object $object, ?string $format = null, array $context = []): array
    {
        return [];
    }

    protected function getAttributeValue(object $object, string $attribute, ?string $format = null, array $context = []): mixed
    {
    }

    protected function setAttributeValue(object $object, string $attribute, $value, ?string $format = null, array $context = []): void
    {
        $object->$attribute = $value;
    }

    protected function isAllowedAttribute($classOrObject, string $attribute, ?string $format = null, array $context = []): bool
    {
        return \in_array($attribute, ['foo', 'baz', 'quux', 'value'], true);
    }

    public function instantiateObject(array &$data, string $class, array &$context, \ReflectionClass $reflectionClass, $allowedAttributes, ?string $format = null): object
    {
        return parent::instantiateObject($data, $class, $context, $reflectionClass, $allowedAttributes, $format);
    }
}

class Dummy
{
    public $foo;
    public $bar;
    public $baz;
}

class EmptyDummy
{
}

class AlreadyPopulatedNestedDummy
{
    #[SerializedPath('[one][two][three]')]
    public $foo;

    #[SerializedPath('[one][two]')]
    public $bar;
}

class DuplicateValueNestedDummy
{
    #[SerializedPath('[one][two][three]')]
    public $foo;

    #[SerializedPath('[one][two][three]')]
    public $bar;

    public $baz;
}

class NestedDummy
{
    #[SerializedPath('[one][two][three]')]
    public $foo;

    #[SerializedPath('[one][four]')]
    public $quux;

    #[SerializedPath('[foo]')]
    public $notfoo;

    public $baz;
}

class NestedDummyWithConstructor
{
    public function __construct(
        #[SerializedPath('[one][two][three]')]
        public $foo,

        #[SerializedPath('[one][four]')]
        public $quux,

        #[SerializedPath('[foo]')]
        public $notfoo,

        public $baz,
    ) {
    }
}

class SnakeCaseNestedDummy
{
    #[SerializedPath('[one][two_three]')]
    public $fooBar;
}

class MixedPropertyDummy
{
    public mixed $foo;
}

#[DiscriminatorMap(typeProperty: 'type', mapping: [
    'first' => FirstNestedDummyWithConstructorAndDiscriminator::class,
    'second' => SecondNestedDummyWithConstructorAndDiscriminator::class,
])]
abstract class AbstractNestedDummyWithConstructorAndDiscriminator
{
    public function __construct(
        #[SerializedPath('[foo]')]
        public $notfoo,

        public $baz,
    ) {
    }
}

class FirstNestedDummyWithConstructorAndDiscriminator extends AbstractNestedDummyWithConstructorAndDiscriminator
{
    public function __construct(
        #[SerializedPath('[one][two][three]')]
        public $foo,

        $notfoo,
        $baz,
    ) {
        parent::__construct($notfoo, $baz);
    }
}

class SecondNestedDummyWithConstructorAndDiscriminator extends AbstractNestedDummyWithConstructorAndDiscriminator
{
    public function __construct(
        #[SerializedPath('[one][four]')]
        public $quux,

        $notfoo,
        $baz,
    ) {
        parent::__construct($notfoo, $baz);
    }
}

class DuplicateKeyNestedDummy
{
    #[SerializedPath('[one][four]')]
    public $quux;

    #[SerializedName('quux')]
    public $notquux;
}

class ObjectDummyWithContextAttributeAndSerializedPath
{
    public function __construct(
        #[Context([DateTimeNormalizer::FORMAT_KEY => 'm-d-Y'])]
        #[SerializedPath('[property][with_path]')]
        public \DateTimeImmutable $propertyWithPath,
    ) {
    }
}

class ObjectDummyWithContextAttributeSkipNullValues
{
    #[Context([AbstractObjectNormalizer::SKIP_NULL_VALUES => true])]
    public ?string $propertyWithoutNullSkipNullValues = 'foo';

    #[Context([AbstractObjectNormalizer::SKIP_NULL_VALUES => true])]
    public ?string $propertyWithNullSkipNullValues = null;
}

class AbstractObjectNormalizerWithMetadata extends AbstractObjectNormalizer
{
    public function __construct()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        parent::__construct($classMetadataFactory, new MetadataAwareNameConverter($classMetadataFactory));
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['*' => false];
    }

    protected function extractAttributes(object $object, ?string $format = null, array $context = []): array
    {
    }

    protected function getAttributeValue(object $object, string $attribute, ?string $format = null, array $context = []): mixed
    {
    }

    protected function setAttributeValue(object $object, string $attribute, $value, ?string $format = null, array $context = []): void
    {
        if (property_exists($object, $attribute)) {
            $object->$attribute = $value;
        }
    }
}

class DummyWithPlainObject
{
    /** @var object */
    public $plainObject;
}

class ObjectWithBasicProperties
{
    /** @var bool */
    public $boolTrue1;

    /** @var bool */
    public $boolFalse1;

    /** @var bool */
    public $boolTrue2;

    /** @var bool */
    public $boolFalse2;

    /** @var int */
    public $int1;

    /** @var int */
    public $int2;

    /** @var float */
    public $float1;

    /** @var float */
    public $float2;

    /** @var float */
    public $float3;

    /** @var float */
    public $floatNaN;

    /** @var float */
    public $floatInf;

    /** @var float */
    public $floatNegInf;
}

class StringCollection
{
    /** @var string[] */
    public $children;
}

class DummyCollection
{
    /** @var DummyChild[] */
    public $children;
}

class DummyChild
{
    public $bar;
}

class XmlScalarDummy
{
    #[SerializedName('#')]
    public $value;
}

class FalsePropertyDummy
{
    /** @var false */
    public $foo;
}

class TruePropertyDummy
{
    /** @var true */
    public $foo;
}

class BoolPropertyDummy
{
    /** @var bool|null */
    public $foo;
}

class DummyWithArrayObject
{
    /** @var \ArrayObject<string, mixed> */
    public $foo;
}

class SerializerCollectionDummy implements SerializerInterface, DenormalizerInterface
{
    private array $normalizers;

    /**
     * @param DenormalizerInterface[] $normalizers
     */
    public function __construct(array $normalizers)
    {
        $this->normalizers = $normalizers;
    }

    public function serialize($data, string $format, array $context = []): string
    {
    }

    public function deserialize($data, string $type, string $format, array $context = []): mixed
    {
    }

    public function denormalize($data, string $type, ?string $format = null, array $context = []): mixed
    {
        foreach ($this->normalizers as $normalizer) {
            if ($normalizer instanceof DenormalizerInterface && $normalizer->supportsDenormalization($data, $type, $format, $context)) {
                return $normalizer->denormalize($data, $type, $format, $context);
            }
        }

        return null;
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['*' => false];
    }

    public function supportsDenormalization($data, string $type, ?string $format = null, array $context = []): bool
    {
        return true;
    }
}

class AbstractObjectNormalizerCollectionDummy extends AbstractObjectNormalizer
{
    public function getSupportedTypes(?string $format): array
    {
        return ['*' => false];
    }

    protected function extractAttributes(object $object, ?string $format = null, array $context = []): array
    {
    }

    protected function getAttributeValue(object $object, string $attribute, ?string $format = null, array $context = []): mixed
    {
    }

    protected function setAttributeValue(object $object, string $attribute, $value, ?string $format = null, array $context = []): void
    {
        $object->$attribute = $value;
    }

    protected function isAllowedAttribute($classOrObject, string $attribute, ?string $format = null, array $context = []): bool
    {
        return true;
    }

    public function instantiateObject(array &$data, string $class, array &$context, \ReflectionClass $reflectionClass, $allowedAttributes, ?string $format = null): object
    {
        return parent::instantiateObject($data, $class, $context, $reflectionClass, $allowedAttributes, $format);
    }

    public function serialize($data, string $format, array $context = [])
    {
    }

    public function deserialize($data, string $type, string $format, array $context = [])
    {
    }
}

class ArrayDenormalizerDummy implements DenormalizerInterface, SerializerAwareInterface
{
    private SerializerInterface&DenormalizerInterface $serializer;

    /**
     * @throws NotNormalizableValueException
     */
    public function denormalize($data, string $type, ?string $format = null, array $context = []): mixed
    {
        $serializer = $this->serializer;
        $type = substr($type, 0, -2);

        foreach ($data as $key => $value) {
            $data[$key] = $serializer->denormalize($value, $type, $format, $context);
        }

        return $data;
    }

    public function getSupportedTypes(?string $format): array
    {
        return $this->serializer->getSupportedTypes($format);
    }

    public function supportsDenormalization($data, string $type, ?string $format = null, array $context = []): bool
    {
        return str_ends_with($type, '[]')
            && $this->serializer->supportsDenormalization($data, substr($type, 0, -2), $format, $context);
    }

    public function setSerializer(SerializerInterface $serializer): void
    {
        \assert($serializer instanceof DenormalizerInterface);
        $this->serializer = $serializer;
    }
}

class NotSerializable
{
    public function __sleep(): array
    {
        throw new \Error('not serializable');
    }
}

enum EnumA: string
{
    case A = 'a';
}

enum EnumB: string
{
    case B = 'b';
}

class DummyWithEnumUnion
{
    public function __construct(
        public readonly EnumA|EnumB $enum,
    ) {
    }
}

#[DiscriminatorMap('type', ['foo' => ScalarCollectionDocBlockDummy::class])]
class ScalarCollectionDocBlockDummy
{
    /**
     * @param array<int, array<int, string>>|null $values
     */
    public function __construct(
        private readonly ?array $values = null,
    ) {
    }

    /** @return array<int, array<int, string>>|null */
    public function getValues(): ?array
    {
        return $this->values;
    }
}

class UnionCollectionDocBlockDummy
{
    /**
     * @param array<string, string|int> $values1
     */
    public function __construct(
        public array $values1,
    ) {
    }

    /**
     * @var array<string, string|int>
     */
    public array $values2;
}

class AbstractObjectNormalizerWithMetadataAndPropertyTypeExtractors extends AbstractObjectNormalizer
{
    public function __construct()
    {
        parent::__construct(new ClassMetadataFactory(new AttributeLoader()), null, new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]));
    }

    protected function extractAttributes(object $object, ?string $format = null, array $context = []): array
    {
        return [];
    }

    protected function getAttributeValue(object $object, string $attribute, ?string $format = null, array $context = []): mixed
    {
        return null;
    }

    protected function setAttributeValue(object $object, string $attribute, mixed $value, ?string $format = null, array $context = []): void
    {
        if (property_exists($object, $attribute)) {
            $object->$attribute = $value;
        }
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            '*' => false,
        ];
    }
}

#[DiscriminatorMap('type', ['dummy' => DummyGenericsValue::class])]
abstract class AbstractDummyGenericsValue
{
    public function __construct(
        public string $type,
    ) {
    }
}

class DummyGenericsValue extends AbstractDummyGenericsValue
{
    public function __construct()
    {
        parent::__construct('dummy');
    }
}

/**
 * @template T of AbstractDummyGenericsValue
 */
class DummyGenericsValueWrapper
{
    /** @var T */
    public mixed $value;
    /** @var T[] */
    public array $values;
}
