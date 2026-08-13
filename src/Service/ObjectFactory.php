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

use Derafu\BackboneDispatcher\Contract\ObjectFactoryInterface;
use Derafu\BackboneDispatcher\Exception\ClassNotFoundException;
use Derafu\BackboneDispatcher\Exception\FromArrayMethodNotFoundException;

class ObjectFactory implements ObjectFactoryInterface
{
    public function create(string $class, array|string|null $data): ?object
    {
        if (!class_exists($class)) {
            throw ClassNotFoundException::forClass($class);
        }

        if (!method_exists($class, 'fromArray')) {
            throw FromArrayMethodNotFoundException::forClass($class);
        }

        return $class::fromArray($data);
    }
}
