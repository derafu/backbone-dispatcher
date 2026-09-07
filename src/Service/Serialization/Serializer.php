<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\BackboneDispatcher\Service\Serialization;

use BackedEnum;
use Derafu\BackboneDispatcher\Contract\SerializerInterface;
use JsonSerializable;
use UnitEnum;

/**
 * Default serializer: recursively flattens arrays and any object following
 * the `JsonSerializable`/`toArray()` conventions already used across the
 * Derafu/LibreDTE domain objects (e.g. `DocumentBag::toArray()`).
 *
 * A native PHP enum case is flattened using its own `name`/`value`
 * properties (not `toArray()`/`JsonSerializable`, which it does not have
 * unless it explicitly opts in, in which case that takes precedence).
 *
 * Values that are neither an array, a `JsonSerializable`, an object exposing
 * `toArray()`, nor an enum case are returned as-is; it is up to the
 * caller/transport to decide what to do with them (e.g. `json_encode()` will
 * fall back to reflecting public properties, a phpy-based transport will get
 * a live object proxy).
 */
class Serializer implements SerializerInterface
{
    public function serialize(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => $this->serialize($item), $value);
        }

        if ($value instanceof JsonSerializable) {
            return $this->serialize($value->jsonSerialize());
        }

        if (is_object($value) && method_exists($value, 'toArray')) {
            return $this->serialize($value->toArray());
        }

        if ($value instanceof UnitEnum) {
            return $this->serializeEnum($value);
        }

        return $value;
    }

    /**
     * Flattens an enum case using the properties PHP itself exposes for it:
     * `name` for every enum, plus `value` when it is backed.
     *
     * @param UnitEnum $enum
     * @return array
     */
    private function serializeEnum(UnitEnum $enum): array
    {
        $data = ['name' => $enum->name];

        if ($enum instanceof BackedEnum) {
            $data['value'] = $enum->value;
        }

        return $data;
    }
}
