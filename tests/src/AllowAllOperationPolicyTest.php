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

use Derafu\BackboneDispatcher\Service\Policy\AllowAllOperationPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AllowAllOperationPolicy::class)]
class AllowAllOperationPolicyTest extends TestCase
{
    public function testAlwaysAllows(): void
    {
        $policy = new AllowAllOperationPolicy();

        $this->assertTrue($policy->isAllowed('any_package', 'any_component', 'any_worker', 'anyOperation'));
    }
}
