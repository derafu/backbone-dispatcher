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
 * Dispatches an `OperationRequestInterface` and returns an
 * `OperationResultInterface` on success.
 *
 * Unlike `SafeDispatcherInterface`, this does NOT catch anything: any
 * `Throwable` from the underlying `DirectDispatcherInterface` is left to
 * propagate unchanged. A successful return here always has
 * `OperationResultInterface::isSuccess() === true` — there is no failure
 * variant coming out of this contract, only out of a thrown exception.
 *
 * The value of wrapping the success case in `OperationResultInterface`
 * anyway, instead of returning the raw value directly, is consistency
 * (typed in, typed out, symmetric with `SafeDispatcherInterface`) and
 * room to grow: if `OperationResultInterface` ever carries more than
 * `value` (execution time, memory used, warnings, ...), every consumer of
 * this interface gets it for free, without a signature change.
 */
interface TypedDispatcherInterface
{
    public function dispatch(OperationRequestInterface $request): OperationResultInterface;
}
