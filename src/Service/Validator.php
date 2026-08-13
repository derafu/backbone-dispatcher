<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\BackboneDispatcher\Service;

/**
 * Validates the type of data of the input parameters.
 */
class Validator
{
    /**
     * Validates the value with the expected data type.
     *
     * @param mixed $value
     * @param string $type
     * @return boolean
     */
    public function validate(mixed $value, string $type): bool
    {
        return match ($type) {
            'int' => is_int($value),
            'string' => is_string($value),
            'bool' => is_bool($value),
            'array' => is_array($value),
            default => true, // Unknown types are not validated.
        };
    }
}
