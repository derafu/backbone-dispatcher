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

use Derafu\BackboneDispatcher\Exception\NoDeserializerFoundException;
use Derafu\BackboneDispatcher\Service\FromArrayDeserializer;
use Derafu\BackboneDispatcher\Service\ObjectFactoryRegistry;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleBag;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleBagDeserializer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ObjectFactoryRegistry::class)]
#[UsesClass(FromArrayDeserializer::class)]
#[UsesClass(NoDeserializerFoundException::class)]
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
}
