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

use Derafu\Translation\Exception\Core\TranslatableLogicException;

/**
 * Exception for a feature that needs an optional (`require-dev`/`suggest`)
 * package this project does not require directly — e.g.
 * `AbstractDeserializer::assertSchema()` needs `opis/json-schema`, which
 * most consumers of this package never install because most deserializers
 * never call it.
 *
 * Not an `ObjectFactoryException`: like `NotImplementedException`, this is
 * not a signal about the *data* a deserializer received, so
 * `ObjectFactoryRegistry` must never treat it as "try the next candidate".
 */
class MissingOptionalDependencyException extends TranslatableLogicException
{
    /**
     * Returns a new exception for a feature whose optional package is not
     * installed.
     *
     * @param string $feature The feature that needs it, as `Class::method()`.
     * @param string $package The Composer package to install.
     * @return self
     */
    public static function forFeature(string $feature, string $package): self
    {
        return new self([
            '{feature} requires {package}, which is not installed. Run: composer require --dev {package}',
            'feature' => $feature,
            'package' => $package,
        ]);
    }
}
