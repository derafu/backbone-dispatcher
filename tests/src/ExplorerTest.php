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

use Derafu\BackboneDispatcher\Exception\InvalidDiscoveryIdException;
use Derafu\BackboneDispatcher\Exception\OperationNotAllowedException;
use Derafu\BackboneDispatcher\Exception\OperationNotFoundException;
use Derafu\BackboneDispatcher\Service\Discovery\Explorer;
use Derafu\BackboneDispatcher\Service\Policy\AllowListOperationPolicy;
use Derafu\BackboneDispatcher\Service\Reflection\Inspector;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleComponent;
use Derafu\TestsBackboneDispatcher\Fixture\ExamplePackage;
use Derafu\TestsBackboneDispatcher\Fixture\ExamplePackageRegistry;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleWorker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Explorer::class)]
#[UsesClass(Inspector::class)]
#[UsesClass(AllowListOperationPolicy::class)]
#[UsesClass(OperationNotFoundException::class)]
#[UsesClass(OperationNotAllowedException::class)]
#[UsesClass(InvalidDiscoveryIdException::class)]
class ExplorerTest extends TestCase
{
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
    }

    public function testGetPackageIncludesTheRealNameAndDescription(): void
    {
        $explorer = new Explorer($this->registry, $this->inspector);

        $data = $explorer->getPackage('example_package');

        $this->assertSame('example_package', $data['id']);
        $this->assertSame('Example Package', $data['name']);
        $this->assertSame('A package holding a fixed set of real components.', $data['description']);
    }

    public function testGetComponentIncludesTheRealNameAndDescription(): void
    {
        $explorer = new Explorer($this->registry, $this->inspector);

        $data = $explorer->getComponent('example_package', 'example_component');

        $this->assertSame('example_package.example_component', $data['id']);
        $this->assertSame('Example Component', $data['name']);
        $this->assertSame('A component holding a fixed set of real workers.', $data['description']);
    }

    public function testGetWorkerIncludesTheRealNameAndDescription(): void
    {
        $explorer = new Explorer($this->registry, $this->inspector);

        $data = $explorer->getWorker('example_package', 'example_component', 'example_worker');

        $this->assertSame('example_package.example_component.example_worker', $data['id']);
        $this->assertSame('Example Worker', $data['name']);
        $this->assertSame('A worker with a few real, reflectable operations.', $data['description']);
    }

    public function testGetOperationReturnsTheDocOfExactlyTheRequestedOperation(): void
    {
        $explorer = new Explorer($this->registry, $this->inspector);

        $data = $explorer->getOperation('example_package', 'example_component', 'example_worker', 'sum');

        $this->assertSame('example_package.example_component.example_worker::sum', $data['id']);
        $this->assertSame('sum', $data['name']);
    }

    public function testGetOperationThrowsOperationNotFoundForANonexistentOperation(): void
    {
        $explorer = new Explorer($this->registry, $this->inspector);

        $this->expectException(OperationNotFoundException::class);

        $explorer->getOperation('example_package', 'example_component', 'example_worker', 'doesNotExist');
    }

    public function testGetOperationThrowsOperationNotAllowedWhenThePolicyRejectsIt(): void
    {
        $explorer = new Explorer(
            $this->registry,
            $this->inspector,
            new AllowListOperationPolicy(['example_package.example_component.example_worker::sum']),
        );

        $this->expectException(OperationNotAllowedException::class);

        $explorer->getOperation('example_package', 'example_component', 'example_worker', 'describeBag');
    }

    public function testGetOperationsListsEverythingWithoutAPolicy(): void
    {
        $explorer = new Explorer($this->registry, $this->inspector);

        $names = array_column(
            $explorer->getOperations('example_package', 'example_component', 'example_worker'),
            'name'
        );

        // Not an exhaustive list on purpose: ExampleWorker's Aware traits
        // (JobsAwareTrait, HandlersAwareTrait, OptionsAwareTrait) contribute
        // their own public methods too — a separate, pre-existing gap in
        // what Inspector counts as an "operation", not something this test
        // is meant to lock in or paper over.
        $this->assertContains('sum', $names);
        $this->assertContains('describeBag', $names);
        $this->assertContains('makeGreeting', $names);
        $this->assertContains('fail', $names);
    }

    public function testGetOperationsFiltersSilentlyByPolicy(): void
    {
        $explorer = new Explorer(
            $this->registry,
            $this->inspector,
            new AllowListOperationPolicy(['example_package.example_component.example_worker::sum']),
        );

        $names = array_column(
            $explorer->getOperations('example_package', 'example_component', 'example_worker'),
            'name'
        );

        $this->assertSame(['sum'], $names);
    }

    public function testWithoutAPolicyNoBranchIsEverPruned(): void
    {
        $explorer = new Explorer($this->registry, $this->inspector);

        $this->assertNotEmpty($explorer->getPackages());
        $this->assertNotEmpty($explorer->getComponents('example_package'));
        $this->assertNotEmpty($explorer->getWorkers('example_package', 'example_component'));
    }

    public function testAWorkerWithZeroAllowedOperationsDisappearsFromTheTree(): void
    {
        // Matches nothing under example_package, so every operation of
        // example_worker is rejected — the worker, its component and its
        // package must all disappear, not just its operations list.
        $explorer = new Explorer(
            $this->registry,
            $this->inspector,
            new AllowListOperationPolicy(['other_package.*']),
        );

        $this->assertSame([], $explorer->getWorkers('example_package', 'example_component'));
        $this->assertSame([], $explorer->getComponents('example_package'));
        $this->assertSame([], $explorer->getPackages());
    }

    public function testAWorkerWithAtLeastOneAllowedOperationSurvives(): void
    {
        $explorer = new Explorer(
            $this->registry,
            $this->inspector,
            new AllowListOperationPolicy(['example_package.example_component.example_worker::sum']),
        );

        $workerIds = array_column(
            $explorer->getWorkers('example_package', 'example_component'),
            'id'
        );

        $this->assertSame(['example_package.example_component.example_worker'], $workerIds);
        $this->assertNotEmpty($explorer->getComponents('example_package'));
        $this->assertNotEmpty($explorer->getPackages());
    }

    public function testDescribeWithNullIdListsPackages(): void
    {
        $explorer = new Explorer($this->registry, $this->inspector);

        $this->assertSame($explorer->getPackages(), $explorer->describe());
        $this->assertSame($explorer->getPackages(), $explorer->describe(null));
    }

    public function testDescribeWithOneSegmentReturnsThePackage(): void
    {
        $explorer = new Explorer($this->registry, $this->inspector);

        $this->assertSame(
            $explorer->getPackage('example_package'),
            $explorer->describe('example_package')
        );
    }

    public function testDescribeWithTwoSegmentsReturnsTheComponent(): void
    {
        $explorer = new Explorer($this->registry, $this->inspector);

        $this->assertSame(
            $explorer->getComponent('example_package', 'example_component'),
            $explorer->describe('example_package.example_component')
        );
    }

    public function testDescribeWithThreeSegmentsReturnsTheWorker(): void
    {
        $explorer = new Explorer($this->registry, $this->inspector);

        $this->assertSame(
            $explorer->getWorker('example_package', 'example_component', 'example_worker'),
            $explorer->describe('example_package.example_component.example_worker')
        );
    }

    public function testDescribeWithFourSegmentsReturnsTheOperation(): void
    {
        $explorer = new Explorer($this->registry, $this->inspector);

        $this->assertSame(
            $explorer->getOperation('example_package', 'example_component', 'example_worker', 'sum'),
            $explorer->describe('example_package.example_component.example_worker::sum')
        );
    }

    public function testDescribeThrowsForMoreThanThreeSegmentsWithoutAnOperation(): void
    {
        $explorer = new Explorer($this->registry, $this->inspector);

        $this->expectException(InvalidDiscoveryIdException::class);

        $explorer->describe('a.b.c.d');
    }

    public function testDescribeThrowsForAnyEmptySegment(): void
    {
        $explorer = new Explorer($this->registry, $this->inspector);

        $this->expectException(InvalidDiscoveryIdException::class);

        $explorer->describe('example_package..example_worker');
    }

    public function testDescribeThrowsWhenThePathBeforeTheOperationHasTheWrongSegmentCount(): void
    {
        $explorer = new Explorer($this->registry, $this->inspector);

        $this->expectException(InvalidDiscoveryIdException::class);

        $explorer->describe('example_package.example_component::sum');
    }

    public function testDescribeThrowsWhenTheOperationAfterTheDoubleColonIsEmpty(): void
    {
        $explorer = new Explorer($this->registry, $this->inspector);

        $this->expectException(InvalidDiscoveryIdException::class);

        $explorer->describe('example_package.example_component.example_worker::');
    }

    public function testDescribePropagatesOperationExceptionsForAnIdWithAnOperation(): void
    {
        $explorer = new Explorer($this->registry, $this->inspector);

        $this->expectException(OperationNotFoundException::class);

        $explorer->describe('example_package.example_component.example_worker::doesNotExist');
    }
}
