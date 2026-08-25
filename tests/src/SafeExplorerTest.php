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

use Derafu\Backbone\Exception\PackageNotFoundException;
use Derafu\BackboneDispatcher\Exception\InvalidDiscoveryIdException;
use Derafu\BackboneDispatcher\Exception\OperationNotFoundException;
use Derafu\BackboneDispatcher\Service\Discovery\Explorer;
use Derafu\BackboneDispatcher\Service\Discovery\SafeExplorer;
use Derafu\BackboneDispatcher\Service\Reflection\Inspector;
use Derafu\BackboneDispatcher\ValueObject\DiscoveryResult;
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
 * Integration test: real registry, real `Inspector` and `Explorer`
 * underneath — exercises the one behavior that only `SafeExplorer` has: it
 * never throws, wrapping every call in a `DiscoveryResult` instead.
 */
#[CoversClass(SafeExplorer::class)]
#[UsesClass(Explorer::class)]
#[UsesClass(Inspector::class)]
#[UsesClass(DiscoveryResult::class)]
#[UsesClass(ProblemDetail::class)]
#[UsesClass(SafeThrowable::class)]
#[UsesClass(InvalidDiscoveryIdException::class)]
#[UsesClass(OperationNotFoundException::class)]
class SafeExplorerTest extends TestCase
{
    private SafeExplorer $explorer;

    protected function setUp(): void
    {
        $worker = new ExampleWorker();
        $component = new ExampleComponent(['example_worker' => $worker]);
        $package = new ExamplePackage(['example_component' => $component]);

        $registry = new ExamplePackageRegistry();
        $registry->registerPackage('example_package', $package);

        $this->explorer = new SafeExplorer(
            new Explorer($registry, new Inspector()),
            environment: 'test',
            debug: true,
        );
    }

    public function testGetPackagesReturnsASuccessfulResult(): void
    {
        $result = $this->explorer->getPackages();

        $this->assertTrue($result->isSuccess());
        $this->assertSame('example_package', $result->getValue()[0]['id']);
        $this->assertNull($result->getProblem());
    }

    public function testGetOperationReturnsASuccessfulResultForARealOperation(): void
    {
        $result = $this->explorer->getOperation(
            'example_package',
            'example_component',
            'example_worker',
            'sum'
        );

        $this->assertTrue($result->isSuccess());
        $this->assertSame(
            'example_package.example_component.example_worker::sum',
            $result->getValue()['id']
        );
    }

    public function testTreeReturnsTheSameNestedStructureAsExplorer(): void
    {
        $result = $this->explorer->tree();

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('description', $result->getValue());
        $this->assertSame('example_package', $result->getValue()['packages'][0]['id']);
        $this->assertArrayHasKey('components', $result->getValue()['packages'][0]);
    }

    public function testDescribeReturnsAFailureResultForAMalformedId(): void
    {
        $result = $this->explorer->describe('a.b.c.d');

        $this->assertFalse($result->isSuccess());
        $this->assertNull($result->getValue());

        $problem = $result->getProblem();
        $this->assertNotNull($problem);
        $this->assertSame('a.b.c.d', $problem->getInstance());
        $this->assertSame(
            InvalidDiscoveryIdException::class,
            $problem->getThrowable()->getClass()
        );
    }

    public function testGetOperationReturnsAFailureResultForANonexistentOperation(): void
    {
        $result = $this->explorer->getOperation(
            'example_package',
            'example_component',
            'example_worker',
            'doesNotExist'
        );

        $this->assertFalse($result->isSuccess());

        $problem = $result->getProblem();
        $this->assertNotNull($problem);
        $this->assertSame(
            'example_package.example_component.example_worker::doesNotExist',
            $problem->getInstance()
        );
        $this->assertSame(
            OperationNotFoundException::class,
            $problem->getThrowable()->getClass()
        );
    }

    public function testGetComponentsReturnsAFailureResultForANonexistentPackage(): void
    {
        $result = $this->explorer->getComponents('does_not_exist');

        $this->assertFalse($result->isSuccess());

        $problem = $result->getProblem();
        $this->assertNotNull($problem);
        $this->assertSame('does_not_exist', $problem->getInstance());
        $this->assertSame(
            PackageNotFoundException::class,
            $problem->getThrowable()->getClass()
        );
    }
}
