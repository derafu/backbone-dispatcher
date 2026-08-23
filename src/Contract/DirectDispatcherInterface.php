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
 * Dispatches an operation (a public method) of a worker registered in the
 * backbone package registry.
 *
 * Note: an "operation" here is just the name of a public method of a
 * worker, resolved via reflection. It is unrelated to `derafu/backbone`'s
 * own `JobInterface`/`#[Job]` (a separate, formally registered service type
 * in the Package/Component/Worker/Job hierarchy) — a worker may expose an
 * operation that internally uses one or more real Job objects, or none at
 * all; the dispatcher does not know or care either way.
 *
 * The most direct of the three dispatchers in this package: no
 * `OperationRequest`/`OperationResult`/`ProblemDetail`, just the 5 raw
 * arguments in, whatever the operation returns out. Transport-agnostic: it
 * does not know about HTTP, CLI or Python. Any exception thrown by the
 * invoked operation is not caught here, it is left to propagate so each
 * transport (or `TypedDispatcherInterface`/`SafeDispatcherInterface`) can
 * decide how to translate it.
 */
interface DirectDispatcherInterface
{
    /**
     * Resolves the worker of the package/component and invokes the
     * operation (public method) on it with the given parameters.
     *
     * @param string $package
     * @param string $component
     * @param string $worker
     * @param string $operation
     * @param array $parameters
     * @return mixed
     */
    public function dispatch(
        string $package,
        string $component,
        string $worker,
        string $operation,
        array $parameters = []
    ): mixed;
}
