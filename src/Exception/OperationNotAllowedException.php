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

use Derafu\Translation\Exception\Logic\TranslatableDomainException;

/**
 * Exception for an operation that exists but is rejected by the active
 * `OperationPolicyInterface`.
 */
class OperationNotAllowedException extends TranslatableDomainException
{
    /**
     * Returns a new exception for an operation rejected by the current
     * operation policy.
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
            'The operation {operation} of {package}.{component}.{worker} is not allowed to be dispatched.',
            'package' => $package,
            'component' => $component,
            'worker' => $worker,
            'operation' => $operation,
        ]);
    }
}
