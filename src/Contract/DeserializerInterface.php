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
 * Builds an instance of one specific class from array/string data.
 *
 * Unlike `ObjectFactoryInterface` (the entry point `Caster` actually talks
 * to), a deserializer is never handed `null` and never handed a union type
 * string (`"A|B"`) — `ObjectFactoryRegistry` resolves both of those before
 * ever calling one. A deserializer only ever deals with exactly one
 * concrete, already-resolved class.
 */
interface DeserializerInterface
{
    /**
     * @param array<string, mixed>|string $data
     * @param string $class
     * @return object
     */
    public function deserialize(array|string $data, string $class): object;
}
