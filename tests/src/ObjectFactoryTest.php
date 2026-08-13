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
use Derafu\BackboneDispatcher\Service\ObjectFactory;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleBag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(ObjectFactory::class)]
#[UsesClass(ClassNotFoundException::class)]
#[UsesClass(FromArrayMethodNotFoundException::class)]
class ObjectFactoryTest extends TestCase
{
    private ObjectFactory $objectFactory;

    protected function setUp(): void
    {
        $this->objectFactory = new ObjectFactory();
    }

    public function testCreatesObjectUsingItsFromArrayMethod(): void
    {
        $bag = $this->objectFactory->create(ExampleBag::class, [
            'name' => 'folios',
            'amount' => 7,
        ]);

        $this->assertInstanceOf(ExampleBag::class, $bag);
        $this->assertSame('folios', $bag->getName());
        $this->assertSame(7, $bag->getAmount());
    }

    public function testThrowsWhenClassDoesNotExist(): void
    {
        $this->expectException(ClassNotFoundException::class);
        $this->expectExceptionMessage('Class Not\Exist\AtAll does not exist.');

        $this->objectFactory->create('Not\Exist\AtAll', []);
    }

    public function testThrowsWhenClassHasNoFromArrayMethod(): void
    {
        $this->expectException(FromArrayMethodNotFoundException::class);
        $this->expectExceptionMessage(sprintf(
            'Method %s::fromArray() does not exist.',
            stdClass::class
        ));

        $this->objectFactory->create(stdClass::class, []);
    }
}
