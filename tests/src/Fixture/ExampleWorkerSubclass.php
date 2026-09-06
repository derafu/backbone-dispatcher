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
 * A worker that extends `ExampleWorker` without redeclaring `sum()` — the
 * exact shape of a real-world subclass that only adds a new operation
 * (`multiply()`) on top of an existing worker, the same way a downstream
 * package extends an upstream worker without touching every one of its
 * inherited methods.
 *
 * Used to verify that an inherited, `#[Operation]`-tagged method
 * (`sum()`, declared on `ExampleWorker`) stays a real, dispatchable,
 * listable operation even though this subclass never redeclares it —
 * regardless of which `OperationPolicyInterface` is asked.
 */
class ExampleWorkerSubclass extends ExampleWorker
{
    /**
     * An operation declared directly on the subclass, alongside the one
     * (`sum()`) inherited unchanged from `ExampleWorker`.
     */
    #[Operation(
        name: 'Multiply',
        description: 'Multiplies two integers together.',
    )]
    public function multiply(int $a, int $b): int
    {
        return $a * $b;
    }
}
