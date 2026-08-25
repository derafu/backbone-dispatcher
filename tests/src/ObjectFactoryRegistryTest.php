<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsBackboneDispatcher;

use Derafu\BackboneDispatcher\Contract\DeserializerInterface;
use Derafu\BackboneDispatcher\Exception\NoDeserializerFoundException;
use Derafu\BackboneDispatcher\Exception\UnsupportedDataTypeException;
use Derafu\BackboneDispatcher\Service\Deserialization\FromArrayDeserializer;
use Derafu\BackboneDispatcher\Service\Deserialization\ObjectFactoryRegistry;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleBag;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleBagDeserializer;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleGreeting;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleGreetingDeserializer;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ObjectFactoryRegistry::class)]
#[UsesClass(FromArrayDeserializer::class)]
#[UsesClass(NoDeserializerFoundException::class)]
#[UsesClass(UnsupportedDataTypeException::class)]
class ObjectFactoryRegistryTest extends TestCase
{
    public function testReturnsNullWhenDataIsNull(): void
    {
        $registry = new ObjectFactoryRegistry();

        $this->assertNull($registry->create(null, ExampleBag::class));
    }

    public function testFallsBackToFromArrayWhenNoMapEntryMatches(): void
    {
        $registry = new ObjectFactoryRegistry(fallback: new FromArrayDeserializer());

        $bag = $registry->create(['name' => 'folios', 'amount' => 3], ExampleBag::class);

        $this->assertInstanceOf(ExampleBag::class, $bag);
        $this->assertSame('folios', $bag->getName());
    }

    public function testMapEntryTakesPriorityOverTheFallback(): void
    {
        $registry = new ObjectFactoryRegistry(
            deserializers: [ExampleBag::class => new ExampleBagDeserializer()],
            fallback: new FromArrayDeserializer(),
        );

        $bag = $registry->create(['name' => 'folios', 'amount' => 3], ExampleBag::class);

        // The registered deserializer marks the name; plain fromArray() would not.
        $this->assertSame('folios (via registry)', $bag->getName());
    }

    public function testResolvesTheFirstMatchingCandidateOfAUnionType(): void
    {
        $registry = new ObjectFactoryRegistry(fallback: new FromArrayDeserializer());

        $bag = $registry->create(
            ['name' => 'folios', 'amount' => 3],
            'Not\A\Real\Class|' . ExampleBag::class
        );

        $this->assertInstanceOf(ExampleBag::class, $bag);
    }

    public function testThrowsWhenNoMapEntryAndNoFallbackAreGiven(): void
    {
        $registry = new ObjectFactoryRegistry();

        $this->expectException(NoDeserializerFoundException::class);
        $this->expectExceptionMessage(sprintf(
            'No deserializer was found able to build an instance of %s.',
            ExampleBag::class
        ));

        $registry->create(['name' => 'folios', 'amount' => 3], ExampleBag::class);
    }

    public function testThrowsWhenFallbackCannotBuildAnyCandidateEither(): void
    {
        $registry = new ObjectFactoryRegistry(fallback: new FromArrayDeserializer());

        $this->expectException(NoDeserializerFoundException::class);

        $registry->create(['x' => 1], 'Not\A\Real\Class');
    }

    /**
     * `ExampleBag::class` is the first candidate and has a registered
     * deserializer, but it is array-only (`ExampleBagDeserializer`
     * extends `AbstractDeserializer` and calls `assertArray()`). Given
     * string data, it must throw `UnsupportedDataTypeException`, which
     * `ObjectFactoryRegistry` catches to move on to the next candidate —
     * `ExampleGreeting::class`, whose deserializer is string-only and
     * accepts it. This is the exact shape of the real-world case that
     * motivated this behavior: a union like `DocumentBagInterface|
     * XmlDocumentInterface|string`, where the first candidate needs an
     * array and a later one needs a string.
     */
    public function testSkipsAnExplicitCandidateThatRejectsTheDataShapeAndTriesTheNextOne(): void
    {
        $registry = new ObjectFactoryRegistry(
            deserializers: [
                ExampleBag::class => new ExampleBagDeserializer(),
                ExampleGreeting::class => new ExampleGreetingDeserializer(),
            ],
        );

        $greeting = $registry->create(
            'hello',
            ExampleBag::class . '|' . ExampleGreeting::class,
        );

        $this->assertInstanceOf(ExampleGreeting::class, $greeting);
        $this->assertSame('hello', $greeting->getMessage());
    }

    /**
     * Same pair of candidates as above, but with array data this time: the
     * first candidate (`ExampleBag::class`) now matches its own shape, so
     * it wins immediately — declaration order still matters when more than
     * one candidate could actually handle the data.
     */
    public function testTheFirstCandidateStillWinsWhenItsShapeActuallyMatches(): void
    {
        $registry = new ObjectFactoryRegistry(
            deserializers: [
                ExampleBag::class => new ExampleBagDeserializer(),
                ExampleGreeting::class => new ExampleGreetingDeserializer(),
            ],
        );

        $bag = $registry->create(
            ['name' => 'folios', 'amount' => 3],
            ExampleBag::class . '|' . ExampleGreeting::class,
        );

        $this->assertInstanceOf(ExampleBag::class, $bag);
    }

    public function testThrowsWhenEveryExplicitCandidateRejectsTheDataShapeAndThereIsNoFallback(): void
    {
        $registry = new ObjectFactoryRegistry(
            deserializers: [
                ExampleBag::class => new ExampleBagDeserializer(),
            ],
        );

        $this->expectException(NoDeserializerFoundException::class);

        // ExampleBag::class is array-only; a string does not match, and
        // there is no fallback to fall through to.
        $registry->create('hello', ExampleBag::class);
    }

    /**
     * A candidate's deserializer accepting the data's shape and then
     * failing for a real, deeper reason (a business rule, not "wrong
     * shape") must propagate that exception immediately — it is not an
     * `UnsupportedDataTypeException`, so it is not treated as "try the
     * next candidate".
     */
    public function testDoesNotSkipACandidateThatThrowsABusinessExceptionAfterAcceptingTheShape(): void
    {
        $businessRuleDeserializer = new class () implements DeserializerInterface {
            public function deserialize(array|string $data, string $class): object
            {
                // Accepts array data (right shape), then fails for an
                // unrelated, deeper reason.
                throw new LogicException('Some business rule was violated.');
            }
        };

        $registry = new ObjectFactoryRegistry(
            deserializers: [
                ExampleBag::class => $businessRuleDeserializer,
                ExampleGreeting::class => new ExampleGreetingDeserializer(),
            ],
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Some business rule was violated.');

        $registry->create(
            ['name' => 'folios', 'amount' => 3],
            ExampleBag::class . '|' . ExampleGreeting::class,
        );
    }
}
