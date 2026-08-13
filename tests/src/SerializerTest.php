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

use Derafu\BackboneDispatcher\Service\Serializer;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleBag;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleGreeting;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(Serializer::class)]
class SerializerTest extends TestCase
{
    private Serializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new Serializer();
    }

    public function testScalarsAndNullAreReturnedAsIs(): void
    {
        $this->assertSame('hello', $this->serializer->serialize('hello'));
        $this->assertSame(42, $this->serializer->serialize(42));
        $this->assertTrue($this->serializer->serialize(true));
        $this->assertNull($this->serializer->serialize(null));
    }

    public function testArraysAreRecursedIntoElementByElement(): void
    {
        $result = $this->serializer->serialize([
            'a' => 1,
            'b' => ['c' => 2, 'd' => new ExampleBag('inner', 5)],
        ]);

        $this->assertSame([
            'a' => 1,
            'b' => ['c' => 2, 'd' => ['name' => 'inner', 'amount' => 5]],
        ], $result);
    }

    public function testObjectWithToArrayIsFlattenedUsingToArray(): void
    {
        $bag = new ExampleBag('folios', 10);

        $this->assertSame([
            'name' => 'folios',
            'amount' => 10,
        ], $this->serializer->serialize($bag));
    }

    public function testJsonSerializableObjectIsFlattenedUsingJsonSerialize(): void
    {
        $greeting = new ExampleGreeting('Hello!');

        $this->assertSame([
            'message' => 'Hello!',
            'reply' => null,
        ], $this->serializer->serialize($greeting));
    }

    public function testNestedJsonSerializableObjectsAreRecursivelyFlattened(): void
    {
        $greeting = new ExampleGreeting('Hello!', new ExampleGreeting('Hi there!'));

        $this->assertSame([
            'message' => 'Hello!',
            'reply' => [
                'message' => 'Hi there!',
                'reply' => null,
            ],
        ], $this->serializer->serialize($greeting));
    }

    public function testObjectWithoutToArrayOrJsonSerializableIsReturnedAsIs(): void
    {
        $object = new stdClass();
        $object->foo = 'bar';

        $this->assertSame($object, $this->serializer->serialize($object));
    }
}
