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
use Derafu\BackboneDispatcher\Contract\SafeDispatcherInterface;
use Derafu\BackboneDispatcher\Testing\Fixture\FixtureRegistry;
use Derafu\BackboneDispatcher\Testing\ScenarioTestCase;
use Derafu\TestsBackboneDispatcher\Fixture\Harness\HarnessServices;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * The scenarios of `tests/fixtures/scenarios`, run by the real harness against
 * the small dispatcher of the tests. `ScenarioTestCaseTest` tests the behavior
 * of the harness that these scenarios do not show.
 */
#[CoversNothing]
class HarnessScenarioTest extends ScenarioTestCase
{
    protected static function scenariosDirectory(): string
    {
        return dirname(__DIR__) . '/fixtures/scenarios';
    }

    protected static function dispatcher(): SafeDispatcherInterface
    {
        return HarnessServices::dispatcher();
    }

    protected static function objectFactory(): ObjectFactoryInterface
    {
        return HarnessServices::objectFactory();
    }

    protected static function fixtures(FixtureRegistry $registry): void
    {
        $registry
            ->fixture('document', static fn (): string => 'doc:abc')
            ->fixture('person', static fn (array $arguments): mixed => $arguments['name'] ?? 'Ana')
        ;
    }
}
