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
 * Converts the value returned by an operation into transport-safe data (arrays,
 * scalars, null), so it can be sent as JSON over HTTP, across a phpy
 * boundary, etc. without every transport having to know the serialization
 * convention of every domain object.
 */
interface SerializerInterface
{
    public function serialize(mixed $value): mixed;
}
