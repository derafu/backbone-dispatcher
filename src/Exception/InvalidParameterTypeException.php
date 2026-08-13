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

/**
 * Exception for a parameter whose value does not match the expected type.
 */
class InvalidParameterTypeException extends ResolverException
{
    /**
     * Returns a new exception for a parameter with an invalid type.
     *
     * @param string $name The name of the parameter.
     * @param string $type The expected type of the parameter.
     * @return self
     */
    public static function forParameter(string $name, string $type): self
    {
        return new self([
            'The parameter {name} must be of type {type}.',
            'name' => $name,
            'type' => $type,
        ]);
    }
}
