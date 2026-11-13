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
 * A deserializer that can tell, without any data, whether it is able to build
 * a given class.
 *
 * `ObjectFactoryRegistry::supports()` uses it to answer for its fallback: a
 * registered deserializer is known by the class it was registered for, but a
 * fallback builds any class that follows its convention.
 */
interface TypeAwareDeserializerInterface extends DeserializerInterface
{
    /**
     * Whether this deserializer can build `$class` from some input.
     *
     * @param string $class
     * @return bool
     */
    public function supports(string $class): bool;
}
