<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\BackboneDispatcher\Exception;

use Derafu\Translation\Exception\Runtime\TranslatableOutOfBoundsException;

/**
 * Exception for an operation that does not exist as a public method of the
 * targeted worker.
 *
 * Thrown regardless of which `OperationPolicyInterface` is active: whether
 * an operation exists is a fact about the worker, not a policy decision.
 */
class OperationNotFoundException extends TranslatableOutOfBoundsException
{
    /**
     * Returns a new exception for an operation that does not exist.
     *
     * @param string $package
     * @param string $component
     * @param string $worker
     * @param string $operation
     * @return self
     */
    public static function forOperation(
        string $package,
        string $component,
        string $worker,
        string $operation
    ): self {
        return new self([
            'The operation {operation} does not exist in {package}.{component}.{worker}.',
            'package' => $package,
            'component' => $component,
            'worker' => $worker,
            'operation' => $operation,
        ]);
    }
}
