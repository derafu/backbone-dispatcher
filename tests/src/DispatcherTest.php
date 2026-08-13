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
use Derafu\BackboneDispatcher\Service\Dispatcher;
use Derafu\BackboneDispatcher\Service\Inspector;
use Derafu\BackboneDispatcher\Service\ObjectFactory;
use Derafu\BackboneDispatcher\Service\Resolver;
use Derafu\BackboneDispatcher\Service\Serializer;
use Derafu\BackboneDispatcher\Service\Validator;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleComponent;
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
 * and invokes jobs through the real Resolver, real php-di/invoker Invoker,
 * and real Serializer, exactly as a transport (HTTP, phpy) would.
 */
#[CoversClass(Dispatcher::class)]
#[UsesClass(Resolver::class)]
#[UsesClass(Inspector::class)]
#[UsesClass(Caster::class)]
#[UsesClass(ObjectFactory::class)]
#[UsesClass(Validator::class)]
#[UsesClass(Serializer::class)]
class DispatcherTest extends TestCase
{
    private Dispatcher $dispatcher;

    protected function setUp(): void
    {
        $worker = new ExampleWorker();
        $component = new ExampleComponent(['example_worker' => $worker]);
        $package = new ExamplePackage(['example_component' => $component]);

        $registry = new ExamplePackageRegistry();
        $registry->registerPackage('example_package', $package);

        $this->dispatcher = new Dispatcher(
            $registry,
            new Resolver(
                new Inspector(),
                new Caster(new ObjectFactory()),
                new Validator()
            ),
            new Invoker(),
            new Serializer()
        );
    }

    public function testInvokesAJobAndReturnsItsScalarResultUnchanged(): void
    {
        $result = $this->dispatcher->invoke(
            'example_package',
            'example_component',
            'example_worker',
            'sum',
            ['a' => 5, 'b' => 7]
        );

        $this->assertSame(12, $result);
    }

    public function testInvokesAJobWithNamedParametersOutOfOrder(): void
    {
        $result = $this->dispatcher->invoke(
            'example_package',
            'example_component',
            'example_worker',
            'sum',
            ['b' => 1, 'a' => 5]
        );

        $this->assertSame(6, $result);
    }

    public function testFlattensADomainObjectReturnedByTheJobUsingTheSerializer(): void
    {
        $result = $this->dispatcher->invoke(
            'example_package',
            'example_component',
            'example_worker',
            'makeGreeting',
            ['name' => 'World']
        );

        $this->assertSame([
            'message' => 'Hello, World!',
            'reply' => [
                'message' => 'Hi there!',
                'reply' => null,
            ],
        ], $result);
    }

    public function testPropagatesExceptionsThrownByTheJobUnaltered(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Something went wrong while running the job.');

        $this->dispatcher->invoke(
            'example_package',
            'example_component',
            'example_worker',
            'fail'
        );
    }
}
