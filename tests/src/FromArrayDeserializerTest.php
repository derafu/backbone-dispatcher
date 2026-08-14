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

use Derafu\BackboneDispatcher\Exception\ClassNotFoundException;
use Derafu\BackboneDispatcher\Exception\FromArrayMethodNotFoundException;
use Derafu\BackboneDispatcher\Service\FromArrayDeserializer;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleBag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(FromArrayDeserializer::class)]
#[UsesClass(ClassNotFoundException::class)]
#[UsesClass(FromArrayMethodNotFoundException::class)]
class FromArrayDeserializerTest extends TestCase
{
    private FromArrayDeserializer $deserializer;

    protected function setUp(): void
    {
        $this->deserializer = new FromArrayDeserializer();
    }

    public function testCreatesObjectUsingItsFromArrayMethod(): void
    {
        $bag = $this->deserializer->deserialize([
            'name' => 'folios',
            'amount' => 7,
        ], ExampleBag::class);

        $this->assertInstanceOf(ExampleBag::class, $bag);
        $this->assertSame('folios', $bag->getName());
        $this->assertSame(7, $bag->getAmount());
    }

    public function testThrowsWhenClassDoesNotExist(): void
    {
        $this->expectException(ClassNotFoundException::class);
        $this->expectExceptionMessage('Class Not\Exist\AtAll does not exist.');

        $this->deserializer->deserialize([], 'Not\Exist\AtAll');
    }

    public function testThrowsWhenClassHasNoFromArrayMethod(): void
    {
        $this->expectException(FromArrayMethodNotFoundException::class);
        $this->expectExceptionMessage(sprintf(
            'Method %s::fromArray() does not exist.',
            stdClass::class
        ));

        $this->deserializer->deserialize([], stdClass::class);
    }
}
