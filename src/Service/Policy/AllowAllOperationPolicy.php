<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\BackboneDispatcher\Service\Policy;

use Derafu\BackboneDispatcher\Contract\OperationPolicyInterface;

/**
 * Allows every operation that exists: the historical behavior of the
 * dispatcher, kept as the default `OperationPolicyInterface` so existing
 * wiring keeps working unchanged.
 *
 * Be deliberate about keeping this as the active policy: "every operation
 * that exists" includes every public method `Inspector` can see, which is
 * not the same set as "every method meant to be called from outside PHP".
 * A worker built on `AbstractWorker` also carries whatever `JobsAwareTrait`,
 * `HandlersAwareTrait` and `OptionsAwareTrait` contribute (`getJobs()`,
 * `setOptions()`, ...), plus `ServiceInterface`'s own `getId()`/`getName()`/
 * `getDescription()` — none of that is business logic, but with this policy
 * active it is just as dispatchable as `sum()` or `build()`. `Inspector`
 * has no way to tell "public method that happens to exist" apart from
 * "operation meant to be exposed" — that line is drawn by whichever
 * `OperationPolicyInterface` is wired in, not by reflection.
 *
 * `TaggedOperationPolicy` draws that line explicitly, via the
 * `#[Operation]` attribute, and is the recommended default for anything
 * meant to be reached from outside PHP — reserve this one for contexts
 * where every worker's entire public surface is genuinely meant to be
 * callable (e.g. a fully trusted, PHP-only in-process caller).
 */
final class AllowAllOperationPolicy implements OperationPolicyInterface
{
    /**
     * {@inheritDoc}
     */
    public function isAllowed(
        string $package,
        string $component,
        string $worker,
        string $operation
    ): bool {
        return true;
    }
}
