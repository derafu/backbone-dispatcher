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
 * a given type. Unlike `DeserializerInterface`, this handles the cases a
 * deserializer never has to: `$data` being `null`, `$class` being a union
 * type string (`"A|B"`) as produced by `Inspector` for union-typed
 * parameters, and `"array"` as one of that union's candidates — which
 * matches `$data` unchanged, without needing a deserializer for it.
 */
interface ObjectFactoryInterface
{
    /**
     * @param array<string, mixed>|string|null $data
     * @param string $class
     * @return array<string, mixed>|object|null
     */
    public function create(array|string|null $data, string $class): array|object|null;
}
