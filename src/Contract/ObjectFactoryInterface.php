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
 * The entry point `Caster` uses to turn array/string data into an object of
 * a given type. Unlike `DeserializerInterface`, this handles the two cases
 * a deserializer never has to: `$data` being `null`, and `$class` being a
 * union type string (`"A|B"`) as produced by `Inspector` for union-typed
 * parameters.
 */
interface ObjectFactoryInterface
{
    /**
     * @param array<string, mixed>|string|null $data
     * @param string $class
     * @return object|null
     */
    public function create(array|string|null $data, string $class): ?object;
}
