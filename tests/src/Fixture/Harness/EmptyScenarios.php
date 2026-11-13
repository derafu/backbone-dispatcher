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

/**
 * A directory that does not exist.
 */
class EmptyScenarios extends AbstractHarnessScenarios
{
    protected static function scenariosDirectory(): string
    {
        return dirname(__DIR__, 3) . '/fixtures/scenarios_missing';
    }
}
