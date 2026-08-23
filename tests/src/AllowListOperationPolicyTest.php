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

use Derafu\BackboneDispatcher\Service\Policy\AllowListOperationPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AllowListOperationPolicy::class)]
class AllowListOperationPolicyTest extends TestCase
{
    public function testAllowsAnExactlyListedOperation(): void
    {
        $policy = new AllowListOperationPolicy([
            'billing.invoice.builder::build',
        ]);

        $this->assertTrue($policy->isAllowed('billing', 'invoice', 'builder', 'build'));
    }

    public function testRejectsAnOperationNotInTheList(): void
    {
        $policy = new AllowListOperationPolicy([
            'billing.invoice.builder::build',
        ]);

        $this->assertFalse($policy->isAllowed('billing', 'invoice', 'builder', 'cancel'));
    }

    public function testWildcardAllowsEveryOperationOfAWorker(): void
    {
        $policy = new AllowListOperationPolicy([
            'billing.invoice.builder::*',
        ]);

        $this->assertTrue($policy->isAllowed('billing', 'invoice', 'builder', 'build'));
        $this->assertTrue($policy->isAllowed('billing', 'invoice', 'builder', 'cancel'));
        $this->assertFalse($policy->isAllowed('billing', 'invoice', 'otherWorker', 'build'));
    }

    public function testWildcardAllowsEveryOperationOfAComponent(): void
    {
        $policy = new AllowListOperationPolicy([
            'billing.invoice.*',
        ]);

        $this->assertTrue($policy->isAllowed('billing', 'invoice', 'builder', 'build'));
        $this->assertTrue($policy->isAllowed('billing', 'invoice', 'otherWorker', 'cancel'));
        $this->assertFalse($policy->isAllowed('billing', 'other_component', 'builder', 'build'));
    }

    public function testWildcardAllowsEveryOperationOfAPackage(): void
    {
        $policy = new AllowListOperationPolicy([
            'billing.*',
        ]);

        $this->assertTrue($policy->isAllowed('billing', 'invoice', 'builder', 'build'));
        $this->assertFalse($policy->isAllowed('other_package', 'invoice', 'builder', 'build'));
    }

    public function testEmptyListRejectsEverything(): void
    {
        $policy = new AllowListOperationPolicy([]);

        $this->assertFalse($policy->isAllowed('billing', 'invoice', 'builder', 'build'));
    }
}
