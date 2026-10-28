<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsBackboneDispatcher\Fixture;

use Derafu\Backbone\Attribute\Operation;

/**
 * Exposes typed operations for the real YAML dispatch and discovery scenarios.
 */
final class WiringWorker extends ExampleWorker
{
    /**
     * @return array{name: string, doubled: int}
     */
    #[Operation]
    public function describeBag(ExampleBag $bag): array
    {
        return parent::describeBag($bag);
    }

    /**
     * Returns a deserialized greeting so the safe dispatcher serializes it again.
     */
    #[Operation]
    public function greet(ExampleGreeting $greeting): ExampleGreeting
    {
        return $greeting;
    }

    /**
     * Raises the same business failure used by the direct-dispatcher tests.
     */
    #[Operation]
    public function fail(): never
    {
        parent::fail();
    }
}
