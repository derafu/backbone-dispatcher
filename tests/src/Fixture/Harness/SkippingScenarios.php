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

use Derafu\BackboneDispatcher\Testing\Fixture\FixtureRegistry;

/**
 * Scenarios that need something that this environment does not have.
 */
class SkippingScenarios extends AbstractHarnessScenarios
{
    protected static function scenariosDirectory(): string
    {
        return dirname(__DIR__, 3) . '/fixtures/scenarios_skipped';
    }

    protected static function fixtures(FixtureRegistry $registry): void
    {
        $registry
            ->requirement('docker', static fn (): string => 'Docker is not available.')
            ->fixture('dockerized', static fn (): string => 'doc:up', ['docker'])
        ;
    }
}
