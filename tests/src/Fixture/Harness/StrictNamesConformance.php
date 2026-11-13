<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsBackboneDispatcher\Fixture\Harness;

use Derafu\BackboneDispatcher\Contract\ObjectFactoryInterface;
use Derafu\BackboneDispatcher\Contract\SafeExplorerInterface;
use Derafu\BackboneDispatcher\Testing\DispatcherConformanceTestCase;

/**
 * The conformance suite over the dispatcher that follows the rules, with a
 * pattern for the names that none of its names matches. It is not run by
 * PHPUnit (its name does not end in `Test`).
 */
class StrictNamesConformance extends DispatcherConformanceTestCase
{
    protected static function explorer(): SafeExplorerInterface
    {
        return HarnessServices::explorer();
    }

    protected static function objectFactory(): ObjectFactoryInterface
    {
        return HarnessServices::objectFactory();
    }

    protected function namePattern(): string
    {
        return '/^[a-z]+$/';
    }
}
