<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsBackboneDispatcher\Fixture;

use Derafu\BackboneDispatcher\Abstract\AbstractDeserializer;
use LogicException;

/**
 * Exposes `AbstractDeserializer`'s protected `assertArray()`/`assertString()`
 * as public methods, so `AbstractDeserializerTest` can exercise them
 * directly without going through a concrete deserializer's own
 * `deserialize()` logic.
 */
class ExampleShapeAssertingDeserializer extends AbstractDeserializer
{
    public function deserialize(array|string $data, string $class): object
    {
        throw new LogicException('Not used by AbstractDeserializerTest.');
    }

    public function callAssertArray(array|string $data): array
    {
        return $this->assertArray($data);
    }

    public function callAssertString(array|string $data): string
    {
        return $this->assertString($data);
    }

    public function callAssertKeys(array $data, array $requiredKeys): array
    {
        return $this->assertKeys($data, $requiredKeys);
    }

    public function callAssertSchema(array $data, array $schema): array
    {
        return $this->assertSchema($data, $schema);
    }
}
