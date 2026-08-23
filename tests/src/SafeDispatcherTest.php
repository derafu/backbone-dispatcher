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

use Derafu\BackboneDispatcher\Service\Deserialization\FromArrayDeserializer;
use Derafu\BackboneDispatcher\Service\Deserialization\ObjectFactoryRegistry;
use Derafu\BackboneDispatcher\Service\Dispatch\DirectDispatcher;
use Derafu\BackboneDispatcher\Service\Dispatch\SafeDispatcher;
use Derafu\BackboneDispatcher\Service\Dispatch\TypedDispatcher;
use Derafu\BackboneDispatcher\Service\Policy\AllowAllOperationPolicy;
use Derafu\BackboneDispatcher\Service\Reflection\Inspector;
use Derafu\BackboneDispatcher\Service\Resolution\Caster;
use Derafu\BackboneDispatcher\Service\Resolution\Resolver;
use Derafu\BackboneDispatcher\Service\Resolution\Validator;
use Derafu\BackboneDispatcher\Service\Serialization\Serializer;
use Derafu\BackboneDispatcher\ValueObject\OperationRequest;
use Derafu\BackboneDispatcher\ValueObject\OperationResult;
use Derafu\BackboneDispatcher\ValueObject\ProblemDetail;
use Derafu\BackboneDispatcher\ValueObject\SafeThrowable;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleComponent;
use Derafu\TestsBackboneDispatcher\Fixture\ExamplePackage;
use Derafu\TestsBackboneDispatcher\Fixture\ExamplePackageRegistry;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleWorker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Integration test: real registry, real Resolver, real TypedDispatcher and
 * Serializer underneath — exercises the one behavior that only
 * `SafeDispatcher` has: it never throws, and it is the only tier that
 * flattens domain objects via the Serializer.
 */
#[CoversClass(SafeDispatcher::class)]
#[UsesClass(TypedDispatcher::class)]
#[UsesClass(DirectDispatcher::class)]
#[UsesClass(Resolver::class)]
#[UsesClass(Inspector::class)]
#[UsesClass(Caster::class)]
#[UsesClass(ObjectFactoryRegistry::class)]
#[UsesClass(FromArrayDeserializer::class)]
#[UsesClass(Validator::class)]
#[UsesClass(AllowAllOperationPolicy::class)]
#[UsesClass(Serializer::class)]
#[UsesClass(OperationRequest::class)]
#[UsesClass(OperationResult::class)]
#[UsesClass(ProblemDetail::class)]
#[UsesClass(SafeThrowable::class)]
class SafeDispatcherTest extends TestCase
{
    private SafeDispatcher $dispatcher;

    protected function setUp(): void
    {
        $worker = new ExampleWorker();
        $component = new ExampleComponent(['example_worker' => $worker]);
        $package = new ExamplePackage(['example_component' => $component]);

        $registry = new ExamplePackageRegistry();
        $registry->registerPackage('example_package', $package);

        $inspector = new Inspector();

        $directDispatcher = new DirectDispatcher(
            $registry,
            $inspector,
            new Resolver(
                $inspector,
                new Caster(new ObjectFactoryRegistry(fallback: new FromArrayDeserializer())),
                new Validator()
            ),
        );

        $this->dispatcher = new SafeDispatcher(
            new TypedDispatcher($directDispatcher),
            new Serializer(),
            environment: 'test',
            debug: true
        );
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

    public function testFlattensADomainObjectReturnedByTheOperationUsingTheSerializer(): void
    {
        $request = new OperationRequest(
            'example_package',
            'example_component',
            'example_worker',
            'makeGreeting',
            ['name' => 'World']
        );

        $result = $this->dispatcher->dispatch($request);

        $this->assertTrue($result->isSuccess());
        $this->assertSame([
            'message' => 'Hello, World!',
            'reply' => [
                'message' => 'Hi there!',
                'reply' => null,
            ],
        ], $result->getValue());
    }

    public function testNeverThrowsAndReturnsAFailureOperationResultInstead(): void
    {
        $request = OperationRequest::fromId(
            'example_package.example_component.example_worker::fail'
        );

        $result = $this->dispatcher->dispatch($request);

        $this->assertFalse($result->isSuccess());
        $this->assertNull($result->getValue());

        $problem = $result->getProblem();
        $this->assertNotNull($problem);
        $this->assertSame(
            'Something went wrong while running the operation.',
            $problem->getDetail()
        );
        $this->assertSame(
            'example_package.example_component.example_worker::fail',
            $problem->getInstance()
        );
        $this->assertSame('RuntimeException', $problem->getThrowable()->getClass());
    }
}
