<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\BackboneDispatcher\Contract;

/**
 * Decides whether a given operation (a public method of a worker) may be
 * dispatched at all, independently of whether it exists.
 *
 * `DirectDispatcherInterface` consults exactly one `OperationPolicyInterface`
 * before invoking an operation, so every tier built on top of it
 * (`TypedDispatcherInterface`, `SafeDispatcherInterface`) enforces the same
 * decision without knowing it exists. Swapping the policy is the only thing
 * needed to change from "any public method is an operation" to "only
 * `#[Operation]`-tagged methods are" or to an explicit allow list — no
 * change to the dispatcher itself.
 */
interface OperationPolicyInterface
{
    /**
     * Decides whether the given operation may be dispatched.
     *
     * @param string $package
     * @param string $component
     * @param string $worker
     * @param string $operation
     * @return bool
     */
    public function isAllowed(
        string $package,
        string $component,
        string $worker,
        string $operation
    ): bool;
}
