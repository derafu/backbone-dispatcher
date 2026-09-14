<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\BackboneDispatcher\Service\Resolution;

/**
 * Validates the type of data of the input parameters.
 */
class Validator
{
    /**
     * Validates the value with the expected data type.
     *
     * A union type (`int|string`) is valid as soon as the value matches any
     * one of its candidates — recursing into this same method for each one,
     * so a candidate that is itself an unknown type (a class/interface name)
     * keeps being permissive exactly like a bare unknown type would.
     *
     * A `'SomeClass[]'` type (see `Caster::resolveCastStrategy()`) is
     * validated the same as a bare `'array'` — the runtime shape the value
     * must have is still an array, regardless of what `cast()` will later
     * hydrate each element into.
     *
     * @param mixed $value
     * @param string $type
     * @return boolean
     */
    public function validate(mixed $value, string $type): bool
    {
        if (str_ends_with($type, '[]')) {
            return is_array($value);
        }

        if (str_contains($type, '|')) {
            foreach (explode('|', $type) as $candidate) {
                if ($this->validate($value, $candidate)) {
                    return true;
                }
            }

            return false;
        }

        return match ($type) {
            'int' => is_int($value),
            'string' => is_string($value),
            'bool' => is_bool($value),
            'array' => is_array($value),
            default => true, // Unknown types are not validated.
        };
    }
}
