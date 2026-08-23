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

use Derafu\BackboneDispatcher\Service\Policy\TaggedOperationPolicy;
use Derafu\BackboneDispatcher\Service\Reflection\Inspector;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleComponent;
use Derafu\TestsBackboneDispatcher\Fixture\ExamplePackage;
use Derafu\TestsBackboneDispatcher\Fixture\ExamplePackageRegistry;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleWorker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TaggedOperationPolicy::class)]
#[UsesClass(Inspector::class)]
class TaggedOperationPolicyTest extends TestCase
{
    private TaggedOperationPolicy $policy;

    protected function setUp(): void
    {
        $worker = new ExampleWorker();
        $component = new ExampleComponent(['example_worker' => $worker]);
        $package = new ExamplePackage(['example_component' => $component]);

        $registry = new ExamplePackageRegistry();
        $registry->registerPackage('example_package', $package);

        $this->policy = new TaggedOperationPolicy($registry, new Inspector());
    }

    public function testAllowsAnOperationTaggedWithOperation(): void
    {
        $this->assertTrue($this->policy->isAllowed(
            'example_package',
            'example_component',
            'example_worker',
            'sum'
        ));
    }

    public function testRejectsAnOperationWithoutTheAttribute(): void
    {
        $this->assertFalse($this->policy->isAllowed(
            'example_package',
            'example_component',
            'example_worker',
            'describeBag'
        ));
    }

    public function testRejectsANonexistentOperation(): void
    {
        $this->assertFalse($this->policy->isAllowed(
            'example_package',
            'example_component',
            'example_worker',
            'doesNotExist'
        ));
    }
}
