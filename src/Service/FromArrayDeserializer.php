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

use Derafu\BackboneDispatcher\Abstract\AbstractDeserializer;
use Derafu\BackboneDispatcher\Exception\ClassNotFoundException;
use Derafu\BackboneDispatcher\Exception\FromArrayMethodNotFoundException;

/**
 * Builds an object by calling the target class's own static `fromArray()`
 * method — the convention-based deserializer: works for any class that
 * follows it, with no registration needed. Typically used as
 * `ObjectFactoryRegistry`'s fallback for classes that have no dedicated
 * `DeserializerInterface` registered.
 */
class FromArrayDeserializer extends AbstractDeserializer
{
    /**
     * {@inheritDoc}
     */
    public function deserialize(array|string $data, string $class): object
    {
        if (!class_exists($class)) {
            throw ClassNotFoundException::forClass($class);
        }

        if (!method_exists($class, 'fromArray')) {
            throw FromArrayMethodNotFoundException::forClass($class);
        }

        $data = $this->assertArray($data);

        return $class::fromArray($data);
    }
}
