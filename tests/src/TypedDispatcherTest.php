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
use Derafu\BackboneDispatcher\Service\TypedDispatcher;
use Derafu\BackboneDispatcher\Service\Validator;
use Derafu\BackboneDispatcher\ValueObject\OperationRequest;
use Derafu\BackboneDispatcher\ValueObject\OperationResult;
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
 * Integration test: real registry, real Resolver, real Invoker underneath —
 * exercises the two behaviors specific to `TypedDispatcher`: it wraps a
 * successful value in `OperationResult` without serializing it (unlike
 * `SafeDispatcher`), and it does NOT catch exceptions (unlike
 * `SafeDispatcher` either) — they must propagate unaltered.
 */
#[CoversClass(TypedDispatcher::class)]
#[UsesClass(DirectDispatcher::class)]
#[UsesClass(Resolver::class)]
#[UsesClass(Inspector::class)]
#[UsesClass(Caster::class)]
#[UsesClass(ObjectFactoryRegistry::class)]
#[UsesClass(FromArrayDeserializer::class)]
#[UsesClass(Validator::class)]
#[UsesClass(OperationRequest::class)]
#[UsesClass(OperationResult::class)]
class TypedDispatcherTest extends TestCase
{
    private TypedDispatcher $dispatcher;

    protected function setUp(): void
    {
        $worker = new ExampleWorker();
        $component = new ExampleComponent(['example_worker' => $worker]);
        $package = new ExamplePackage(['example_component' => $component]);

        $registry = new ExamplePackageRegistry();
        $registry->registerPackage('example_package', $package);

        $directDispatcher = new DirectDispatcher(
            $registry,
            new Resolver(
                new Inspector(),
                new Caster(new ObjectFactoryRegistry(fallback: new FromArrayDeserializer())),
                new Validator()
            ),
            new Invoker()
        );

        $this->dispatcher = new TypedDispatcher($directDispatcher);
    }

    public function testWrapsAScalarResultAsASuccessfulOperationResult(): void
    {
        $request = new OperationRequest(
            'example_package',
            'example_component',
            'example_worker',
            'sum',
            ['a' => 5, 'b' => 7]
        );

        $result = $this->dispatcher->dispatch($request);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(12, $result->getValue());
        $this->assertNull($result->getProblem());
    }

    public function testDoesNotSerializeADomainObjectReturnedByTheOperation(): void
    {
        $request = new OperationRequest(
            'example_package',
            'example_component',
            'example_worker',
            'makeGreeting',
            ['name' => 'World']
        );

        $result = $this->dispatcher->dispatch($request);

        // Unlike SafeDispatcher, the raw domain object is returned as-is —
        // serialization is not this tier's responsibility.
        $this->assertInstanceOf(ExampleGreeting::class, $result->getValue());
        $this->assertSame('Hello, World!', $result->getValue()->getMessage());
    }

    public function testDoesNotCatchExceptionsThrownByTheOperation(): void
    {
        $request = OperationRequest::fromId(
            'example_package.example_component.example_worker:fail'
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Something went wrong while running the operation.');

        $this->dispatcher->dispatch($request);
    }
}
