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
 * Dispatches an `OperationRequestInterface` and ALWAYS returns an
 * `OperationResultInterface` — success or failure, never throws.
 *
 * Any `Throwable` from the underlying dispatch is caught and turned into
 * `OperationResultInterface::failure()` with a `ProblemDetailInterface`
 * describing it. Intended for callers at a boundary that cannot (or should
 * not) rely on PHP exceptions to signal failure — e.g. a foreign-language
 * caller through `phpy`, which does not propagate uncaught exception
 * messages across the boundary.
 */
interface SafeDispatcherInterface
{
    public function dispatch(OperationRequestInterface $request): OperationResultInterface;
}
