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
 * Invokes a job of a worker registered in the backbone package registry.
 *
 * Transport-agnostic: it does not know about HTTP, CLI or Python. Any
 * exception thrown by the invoked job is not caught here, it is left to
 * propagate so each transport can decide how to translate it.
 */
interface DispatcherInterface
{
    /**
     * Resolves the worker of the package/component and invokes the job
     * (public method) on it with the given parameters.
     *
     * @param string $package
     * @param string $component
     * @param string $worker
     * @param string $job
     * @param array $params
     * @return mixed
     */
    public function invoke(
        string $package,
        string $component,
        string $worker,
        string $job,
        array $params = []
    ): mixed;
}
