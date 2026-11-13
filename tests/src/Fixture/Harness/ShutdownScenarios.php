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

use ArrayObject;
use Derafu\BackboneDispatcher\Testing\Fixture\FixtureRegistry;

/**
 * Scenarios whose fixtures register a shutdown hook.
 */
class ShutdownScenarios extends AbstractHarnessScenarios
{
    /**
     * @var ArrayObject<int, bool>|null
     */
    private static ?ArrayObject $calls = null;

    public static function shutdowns(): int
    {
        return count(self::calls());
    }

    public static function reset(): void
    {
        self::$calls = new ArrayObject();
    }

    /**
     * @return ArrayObject<int, bool>
     */
    private static function calls(): ArrayObject
    {
        return self::$calls ??= new ArrayObject();
    }

    protected static function scenariosDirectory(): string
    {
        return dirname(__DIR__, 3) . '/fixtures/scenarios';
    }

    protected static function fixtures(FixtureRegistry $registry): void
    {
        $registry->onShutdown(static function (): void {
            self::calls()->append(true);
        });
    }
}
