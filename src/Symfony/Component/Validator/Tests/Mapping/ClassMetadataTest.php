<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Mapping;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Cascade;
use Symfony\Component\Validator\Constraints\Composite;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\GroupDefinitionException;
use Symfony\Component\Validator\Mapping\CascadingStrategy;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Tests\Fixtures\CascadingEntity;
use Symfony\Component\Validator\Tests\Fixtures\CascadingEntityIntersection;
use Symfony\Component\Validator\Tests\Fixtures\CascadingEntityUnion;
use Symfony\Component\Validator\Tests\Fixtures\ClassConstraint;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintA;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintB;
use Symfony\Component\Validator\Tests\Fixtures\GroupSequenceProviderChildEntity;
use Symfony\Component\Validator\Tests\Fixtures\NestedAttribute\Entity;
use Symfony\Component\Validator\Tests\Fixtures\NestedAttribute\EntityParent;
use Symfony\Component\Validator\Tests\Fixtures\NestedAttribute\GroupSequenceProviderEntity;
use Symfony\Component\Validator\Tests\Fixtures\PropertyConstraint;

class ClassMetadataTest extends TestCase
{
    private const CLASSNAME = Entity::class;
    private const PARENTCLASS = EntityParent::class;
    private const PROVIDERCLASS = GroupSequenceProviderEntity::class;
    private const PROVIDERCHILDCLASS = GroupSequenceProviderChildEntity::class;

    protected ClassMetadata $metadata;

    protected function setUp(): void
    {
        $this->metadata = new ClassMetadata(self::CLASSNAME);
    }

    public function testAddConstraintDoesNotAcceptValid()
    {
        $this->expectException(ConstraintDefinitionException::class);

        $this->metadata->addConstraint(new Valid());
    }

    public function testAddConstraintRequiresClassConstraints()
    {
        $this->expectException(ConstraintDefinitionException::class);

        $this->metadata->addConstraint(new PropertyConstraint());
    }

    public function testAddCompositeConstraintRejectsNestedPropertyConstraints()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The constraint "Symfony\Component\Validator\Tests\Fixtures\PropertyConstraint" cannot be put on classes.');

        $this->metadata->addConstraint(new ClassCompositeConstraint([new PropertyConstraint()]));
    }

    public function testAddCompositeConstraintAcceptsNestedClassConstraints()
    {
        $this->metadata->addConstraint($constraint = new ClassCompositeConstraint([new ClassConstraint()]));
        $this->assertSame($this->metadata->getConstraints(), [$constraint]);
    }

    public function testAddPropertyConstraints()
    {
        $this->metadata->addPropertyConstraint('firstName', new ConstraintA());
        $this->metadata->addPropertyConstraint('lastName', new ConstraintB());

        $this->assertEquals(['firstName', 'lastName'], $this->metadata->getConstrainedProperties());
    }

    public function testAddMultiplePropertyConstraints()
    {
        $this->metadata->addPropertyConstraints('lastName', [new ConstraintA(), new ConstraintB()]);

        $constraints = [
            new ConstraintA(null, null, ['Default', 'Entity']),
            new ConstraintB(null, ['Default', 'Entity']),
        ];

        $properties = $this->metadata->getPropertyMetadata('lastName');

        $this->assertCount(1, $properties);
        $this->assertEquals('lastName', $properties[0]->getName());
        $this->assertEquals($constraints, $properties[0]->getConstraints());
    }

    public function testAddGetterConstraints()
    {
        $this->metadata->addGetterConstraint('lastName', new ConstraintA());
        $this->metadata->addGetterConstraint('lastName', new ConstraintB());

        $constraints = [
            new ConstraintA(null, null, ['Default', 'Entity']),
            new ConstraintB(null, ['Default', 'Entity']),
        ];

        $properties = $this->metadata->getPropertyMetadata('lastName');

        $this->assertCount(1, $properties);
        $this->assertEquals('getLastName', $properties[0]->getName());
        $this->assertEquals($constraints, $properties[0]->getConstraints());
    }

    public function testAddMultipleGetterConstraints()
    {
        $this->metadata->addGetterConstraints('lastName', [new ConstraintA(), new ConstraintB()]);

        $constraints = [
            new ConstraintA(null, null, ['Default', 'Entity']),
            new ConstraintB(null, ['Default', 'Entity']),
        ];

        $properties = $this->metadata->getPropertyMetadata('lastName');

        $this->assertCount(1, $properties);
        $this->assertEquals('getLastName', $properties[0]->getName());
        $this->assertEquals($constraints, $properties[0]->getConstraints());
    }

    public function testMergeConstraintsMergesClassConstraints()
    {
        $parent = new ClassMetadata(self::PARENTCLASS);
        $parent->addConstraint(new ConstraintA());

        $this->metadata->mergeConstraints($parent);
        $this->metadata->addConstraint(new ConstraintA());

        $constraints = [
            new ConstraintA(null, null, [
                'Default',
                'EntityParent',
                'Entity',
            ]),
            new ConstraintA(null, null, [
                'Default',
                'Entity',
            ]),
        ];

        $this->assertEquals($constraints, $this->metadata->getConstraints());
    }

    public function testMergeConstraintsMergesMemberConstraints()
    {
        $parent = new ClassMetadata(self::PARENTCLASS);
        $parent->addPropertyConstraint('firstName', new ConstraintA());
        $parent->addPropertyConstraint('firstName', new ConstraintB(null, ['foo']));

        $this->metadata->addPropertyConstraint('firstName', new ConstraintA());
        $this->metadata->mergeConstraints($parent);

        $constraintA1 = new ConstraintA(null, null, [
            'Default',
            'EntityParent',
            'Entity',
        ]);
        $constraintA2 = new ConstraintA(null, null, [
            'Default',
            'Entity',
        ]);
        $constraintB = new ConstraintB(null, ['foo']);

        $members = $this->metadata->getPropertyMetadata('firstName');

        $this->assertCount(2, $members);
        $this->assertEquals(self::CLASSNAME, $members[0]->getClassName());
        $this->assertEquals([$constraintA2], $members[0]->getConstraints());
        $this->assertEquals(
            [
                'Default' => [$constraintA2],
                'Entity' => [$constraintA2],
            ],
            $members[0]->constraintsByGroup
        );
        $this->assertEquals(self::PARENTCLASS, $members[1]->getClassName());
        $this->assertEquals([$constraintA1, $constraintB], $members[1]->getConstraints());
        $this->assertEquals(
            [
                'Default' => [$constraintA1],
                'Entity' => [$constraintA1],
                'EntityParent' => [$constraintA1],
                'foo' => [$constraintB],
            ],
            $members[1]->constraintsByGroup
        );
    }

    public function testMemberMetadatas()
    {
        $this->metadata->addPropertyConstraint('firstName', new ConstraintA());

        $this->assertTrue($this->metadata->hasPropertyMetadata('firstName'));
        $this->assertFalse($this->metadata->hasPropertyMetadata('non_existent_field'));
    }

    public function testMergeConstraintsKeepsPrivateMembersSeparate()
    {
        $parent = new ClassMetadata(self::PARENTCLASS);
        $parent->addPropertyConstraint('internal', new ConstraintA());

        $this->metadata->mergeConstraints($parent);
        $this->metadata->addPropertyConstraint('internal', new ConstraintA());

        $parentConstraints = [
            new ConstraintA(null, null, [
                'Default',
                'EntityParent',
                'Entity',
            ]),
        ];
        $constraints = [
            new ConstraintA(null, null, [
                'Default',
                'Entity',
            ]),
        ];

        $members = $this->metadata->getPropertyMetadata('internal');

        $this->assertCount(2, $members);
        $this->assertEquals(self::PARENTCLASS, $members[0]->getClassName());
        $this->assertEquals($parentConstraints, $members[0]->getConstraints());
        $this->assertEquals(self::CLASSNAME, $members[1]->getClassName());
        $this->assertEquals($constraints, $members[1]->getConstraints());
    }

    public function testGetReflectionClass()
    {
        $reflClass = new \ReflectionClass(self::CLASSNAME);

        $this->assertEquals($reflClass, $this->metadata->getReflectionClass());
    }

    public function testSerialize()
    {
        $this->metadata->addConstraint(new ConstraintA('A'));
        $this->metadata->addConstraint(new ConstraintB(null, ['TestGroup']));
        $this->metadata->addPropertyConstraint('firstName', new ConstraintA());
        $this->metadata->addGetterConstraint('lastName', new ConstraintB());

        $metadata = unserialize(serialize($this->metadata));

        $this->assertEquals($this->metadata, $metadata);
    }

    public function testGroupSequencesWorkIfContainingDefaultGroup()
    {
        $this->metadata->setGroupSequence(['Foo', $this->metadata->getDefaultGroup()]);

        $this->assertInstanceOf(GroupSequence::class, $this->metadata->getGroupSequence());
    }

    public function testGroupSequencesFailIfNotContainingDefaultGroup()
    {
        $this->expectException(GroupDefinitionException::class);
        $this->metadata->setGroupSequence(['Foo', 'Bar']);
    }

    public function testGroupSequencesFailIfContainingDefault()
    {
        $this->expectException(GroupDefinitionException::class);
        $this->metadata->setGroupSequence(['Foo', $this->metadata->getDefaultGroup(), Constraint::DEFAULT_GROUP]);
    }

    public function testGroupSequenceFailsIfGroupSequenceProviderIsSet()
    {
        $metadata = new ClassMetadata(self::PROVIDERCLASS);
        $metadata->setGroupSequenceProvider(true);

        $this->expectException(GroupDefinitionException::class);

        $metadata->setGroupSequence(['GroupSequenceProviderEntity', 'Foo']);
    }

    public function testGroupSequenceProviderFailsIfGroupSequenceIsSet()
    {
        $metadata = new ClassMetadata(self::PROVIDERCLASS);
        $metadata->setGroupSequence(['GroupSequenceProviderEntity', 'Foo']);

        $this->expectException(GroupDefinitionException::class);

        $metadata->setGroupSequenceProvider(true);
    }

    public function testGroupSequenceProviderFailsIfDomainClassIsInvalid()
    {
        $this->expectException(GroupDefinitionException::class);
        $metadata = new ClassMetadata('stdClass');
        $metadata->setGroupSequenceProvider(true);
    }

    public function testGroupSequenceProvider()
    {
        $metadata = new ClassMetadata(self::PROVIDERCLASS);
        $metadata->setGroupSequenceProvider(true);
        $this->assertTrue($metadata->isGroupSequenceProvider());
    }

    public function testMergeConstraintsMergesGroupSequenceProvider()
    {
        $parent = new ClassMetadata(self::PROVIDERCLASS);
        $parent->setGroupSequenceProvider(true);

        $metadata = new ClassMetadata(self::PROVIDERCHILDCLASS);
        $metadata->mergeConstraints($parent);

        $this->assertTrue($metadata->isGroupSequenceProvider());
    }

    /**
     * https://github.com/symfony/symfony/issues/11604.
     */
    public function testGetPropertyMetadataReturnsEmptyArrayWithoutConfiguredMetadata()
    {
        $this->assertCount(0, $this->metadata->getPropertyMetadata('foo'), '->getPropertyMetadata() returns an empty collection if no metadata is configured for the given property');
    }

    public function testCascadeConstraint()
    {
        $metadata = new ClassMetadata(CascadingEntity::class);

        $metadata->addConstraint(new Cascade());

        $this->assertSame(CascadingStrategy::CASCADE, $metadata->getCascadingStrategy());
        $this->assertCount(4, $metadata->properties);
        $this->assertSame([
            'requiredChild',
            'optionalChild',
            'staticChild',
            'children',
        ], $metadata->getConstrainedProperties());
    }

    public function testCascadeConstraintWithUnionTypeProperties()
    {
        $metadata = new ClassMetadata(CascadingEntityUnion::class);
        $metadata->addConstraint(new Cascade());

        $this->assertSame(CascadingStrategy::CASCADE, $metadata->getCascadingStrategy());
        $this->assertCount(5, $metadata->properties);
        $this->assertSame([
            'classes',
            'classAndArray',
            'classAndNull',
            'arrayAndNull',
            'classAndArrayAndNull',
        ], $metadata->getConstrainedProperties());
    }

    public function testCascadeConstraintWithIntersectionTypeProperties()
    {
        $metadata = new ClassMetadata(CascadingEntityIntersection::class);
        $metadata->addConstraint(new Cascade());

        $this->assertSame(CascadingStrategy::CASCADE, $metadata->getCascadingStrategy());
        $this->assertCount(1, $metadata->properties);
        $this->assertSame([
            'classes',
        ], $metadata->getConstrainedProperties());
    }

    public function testCascadeConstraintWithExcludedProperties()
    {
        $metadata = new ClassMetadata(CascadingEntity::class);

        $metadata->addConstraint(new Cascade(exclude: ['requiredChild', 'optionalChild']));

        $this->assertSame(CascadingStrategy::CASCADE, $metadata->getCascadingStrategy());
        $this->assertCount(2, $metadata->properties);
        $this->assertSame([
            'staticChild',
            'children',
        ], $metadata->getConstrainedProperties());
    }
}

class ClassCompositeConstraint extends Composite
{
    public $nested;

    public function __construct(array $nested)
    {
        $this->nested = $nested;

        parent::__construct();
    }

    protected function getCompositeOption(): string
    {
        return 'nested';
    }

    public function getTargets(): string|array
    {
        return [self::CLASS_CONSTRAINT];
    }
}
