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
use Derafu\BackboneDispatcher\Contract\SafeDispatcherInterface;
use Derafu\BackboneDispatcher\Testing\ScenarioTestCase;

/**
 * A case of the harness over the small dispatcher of the tests. It is not run
 * by PHPUnit (its name does not end in `Test`): `ScenarioTestCaseTest` runs it.
 */
abstract class AbstractHarnessScenarios extends ScenarioTestCase
{
    protected static function dispatcher(): SafeDispatcherInterface
    {
        return HarnessServices::dispatcher();
    }

    protected static function objectFactory(): ObjectFactoryInterface
    {
        return HarnessServices::objectFactory();
    }
}
