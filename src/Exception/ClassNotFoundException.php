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
 * Exception for a class that does not exist and cannot be created.
 */
class ClassNotFoundException extends ObjectFactoryException
{
    /**
     * Returns a new exception for a class that does not exist.
     *
     * @param string $class The name of the class.
     * @return self
     */
    public static function forClass(string $class): self
    {
        return new self([
            'Class {class} does not exist.',
            'class' => $class,
        ]);
    }
}
