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

use Derafu\BackboneDispatcher\Service\Caster;
use Derafu\BackboneDispatcher\Service\DirectDispatcher;
use Derafu\BackboneDispatcher\Service\FromArrayDeserializer;
use Derafu\BackboneDispatcher\Service\Inspector;
use Derafu\BackboneDispatcher\Service\ObjectFactoryRegistry;
use Derafu\BackboneDispatcher\Service\Resolver;
use Derafu\BackboneDispatcher\Service\Validator;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleComponent;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleGreeting;
use Derafu\TestsBackboneDispatcher\Fixture\ExamplePackage;
use Derafu\TestsBackboneDispatcher\Fixture\ExamplePackageRegistry;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleWorker;
use Invoker\Invoker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Integration test: builds a real (small) package/component/worker registry
 * and dispatches operations through the real Resolver and real
 * php-di/invoker Invoker. No serialization happens here — that is
 * `SafeDispatcher`'s responsibility, exercised in its own test.
 */
#[CoversClass(DirectDispatcher::class)]
#[UsesClass(Resolver::class)]
#[UsesClass(Inspector::class)]
#[UsesClass(Caster::class)]
#[UsesClass(ObjectFactoryRegistry::class)]
#[UsesClass(FromArrayDeserializer::class)]
#[UsesClass(Validator::class)]
class DirectDispatcherTest extends TestCase
{
    private DirectDispatcher $dispatcher;

    protected function setUp(): void
    {
        $worker = new ExampleWorker();
        $component = new ExampleComponent(['example_worker' => $worker]);
        $package = new ExamplePackage(['example_component' => $component]);

        $registry = new ExamplePackageRegistry();
        $registry->registerPackage('example_package', $package);

        $this->dispatcher = new DirectDispatcher(
            $registry,
            new Resolver(
                new Inspector(),
                new Caster(new ObjectFactoryRegistry(fallback: new FromArrayDeserializer())),
                new Validator()
            ),
            new Invoker()
        );
    }

    public function testDispatchesAnOperationAndReturnsItsScalarResultUnchanged(): void
    {
        $result = $this->dispatcher->dispatch(
            'example_package',
            'example_component',
            'example_worker',
            'sum',
            ['a' => 5, 'b' => 7]
        );

        $this->assertSame(12, $result);
    }

    public function testDispatchesAnOperationWithNamedParametersOutOfOrder(): void
    {
        $result = $this->dispatcher->dispatch(
            'example_package',
            'example_component',
            'example_worker',
            'sum',
            ['b' => 1, 'a' => 5]
        );

        $this->assertSame(6, $result);
    }

    public function testReturnsADomainObjectReturnedByTheOperationUnaltered(): void
    {
        $result = $this->dispatcher->dispatch(
            'example_package',
            'example_component',
            'example_worker',
            'makeGreeting',
            ['name' => 'World']
        );

        $this->assertInstanceOf(ExampleGreeting::class, $result);
        $this->assertSame('Hello, World!', $result->getMessage());
    }

    public function testPropagatesExceptionsThrownByTheOperationUnaltered(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Something went wrong while running the operation.');

        $this->dispatcher->dispatch(
            'example_package',
            'example_component',
            'example_worker',
            'fail'
        );
    }
}
