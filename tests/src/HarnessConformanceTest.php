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

use Derafu\BackboneDispatcher\Contract\ObjectFactoryInterface;
use Derafu\BackboneDispatcher\Contract\SafeExplorerInterface;
use Derafu\BackboneDispatcher\Testing\DispatcherConformanceTestCase;
use Derafu\TestsBackboneDispatcher\Fixture\Harness\HarnessServices;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * The conformance suite, run by the real harness against the small dispatcher
 * of the tests, which follows every rule. `ConformanceTestCaseTest` shows that
 * the suite finds the violations of one that does not.
 */
#[CoversNothing]
class HarnessConformanceTest extends DispatcherConformanceTestCase
{
    protected static function explorer(): SafeExplorerInterface
    {
        return HarnessServices::explorer();
    }

    protected static function objectFactory(): ObjectFactoryInterface
    {
        return HarnessServices::objectFactory();
    }

    protected function parameterNamePattern(): ?string
    {
        return '/^[a-z][a-zA-Z0-9]*$/';
    }
}
