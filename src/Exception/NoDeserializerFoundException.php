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
 * Exception for a type with no registered `DeserializerInterface` able to
 * handle it, and no fallback able to build it either.
 */
class NoDeserializerFoundException extends ObjectFactoryException
{
    /**
     * Returns a new exception for a type with no deserializer able to
     * handle it.
     *
     * @param string $class The requested type (may be a union type string).
     * @return self
     */
    public static function forClass(string $class): self
    {
        return new self([
            'No deserializer was found able to build an instance of {class}.',
            'class' => $class,
        ]);
    }
}
