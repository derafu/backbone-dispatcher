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

use Derafu\BackboneDispatcher\Exception\OperationNotAllowedException;
use Derafu\BackboneDispatcher\Exception\OperationNotFoundException;
use Derafu\BackboneDispatcher\Service\Deserialization\FromArrayDeserializer;
use Derafu\BackboneDispatcher\Service\Deserialization\ObjectFactoryRegistry;
use Derafu\BackboneDispatcher\Service\Dispatch\DirectDispatcher;
use Derafu\BackboneDispatcher\Service\Policy\AllowAllOperationPolicy;
use Derafu\BackboneDispatcher\Service\Policy\AllowListOperationPolicy;
use Derafu\BackboneDispatcher\Service\Reflection\Inspector;
use Derafu\BackboneDispatcher\Service\Resolution\Caster;
use Derafu\BackboneDispatcher\Service\Resolution\Resolver;
use Derafu\BackboneDispatcher\Service\Resolution\Validator;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleComponent;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleGreeting;
use Derafu\TestsBackboneDispatcher\Fixture\ExamplePackage;
use Derafu\TestsBackboneDispatcher\Fixture\ExamplePackageRegistry;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleWorker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Integration test: builds a real (small) package/component/worker registry
 * and dispatches operations through the real Resolver, invoking the worker
 * directly (no invoker library involved). No serialization happens here —
 * that is `SafeDispatcher`'s responsibility, exercised in its own test.
 */
#[CoversClass(DirectDispatcher::class)]
#[UsesClass(Resolver::class)]
#[UsesClass(Inspector::class)]
#[UsesClass(Caster::class)]
#[UsesClass(ObjectFactoryRegistry::class)]
#[UsesClass(FromArrayDeserializer::class)]
#[UsesClass(Validator::class)]
#[UsesClass(AllowAllOperationPolicy::class)]
#[UsesClass(AllowListOperationPolicy::class)]
#[UsesClass(OperationNotFoundException::class)]
#[UsesClass(OperationNotAllowedException::class)]
class DirectDispatcherTest extends TestCase
{
    private DirectDispatcher $dispatcher;

    private ExamplePackageRegistry $registry;

    private Inspector $inspector;

    protected function setUp(): void
    {
        $worker = new ExampleWorker();
        $component = new ExampleComponent(['example_worker' => $worker]);
        $package = new ExamplePackage(['example_component' => $component]);

        $this->registry = new ExamplePackageRegistry();
        $this->registry->registerPackage('example_package', $package);

        $this->inspector = new Inspector();

        $this->dispatcher = new DirectDispatcher(
            $this->registry,
            $this->inspector,
            new Resolver(
                $this->inspector,
                new Caster(new ObjectFactoryRegistry(fallback: new FromArrayDeserializer())),
                new Validator()
            ),
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

    public function testThrowsOperationNotFoundForANonexistentMethod(): void
    {
        $this->expectException(OperationNotFoundException::class);
        $this->expectExceptionMessage(
            'The operation doesNotExist does not exist in example_package.example_component.example_worker.'
        );

        $this->dispatcher->dispatch(
            'example_package',
            'example_component',
            'example_worker',
            'doesNotExist'
        );
    }

    public function testThrowsOperationNotAllowedWhenThePolicyRejectsAnExistingOperation(): void
    {
        $dispatcher = new DirectDispatcher(
            $this->registry,
            $this->inspector,
            new Resolver(
                $this->inspector,
                new Caster(new ObjectFactoryRegistry(fallback: new FromArrayDeserializer())),
                new Validator()
            ),
            new AllowListOperationPolicy(['example_package.example_component.example_worker::sum']),
        );

        $this->expectException(OperationNotAllowedException::class);
        $this->expectExceptionMessage(
            'The operation makeGreeting of example_package.example_component.example_worker is not allowed to be dispatched.'
        );

        $dispatcher->dispatch(
            'example_package',
            'example_component',
            'example_worker',
            'makeGreeting',
            ['name' => 'World']
        );
    }

    public function testAllowListOperationPolicyStillPermitsTheListedOperation(): void
    {
        $dispatcher = new DirectDispatcher(
            $this->registry,
            $this->inspector,
            new Resolver(
                $this->inspector,
                new Caster(new ObjectFactoryRegistry(fallback: new FromArrayDeserializer())),
                new Validator()
            ),
            new AllowListOperationPolicy(['example_package.example_component.example_worker::sum']),
        );

        $result = $dispatcher->dispatch(
            'example_package',
            'example_component',
            'example_worker',
            'sum',
            ['a' => 5, 'b' => 7]
        );

        $this->assertSame(12, $result);
    }
}
