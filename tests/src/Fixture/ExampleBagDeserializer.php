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

/**
 * A deliberately distinguishable deserializer for `ExampleBag`: it marks
 * the name, so tests can prove an `ObjectFactoryRegistry` map entry was
 * used instead of `ExampleBag`'s own `fromArray()` (the fallback), even
 * though both could build the same class.
 *
 * Array-only (via `assertArray()`), paired with `ExampleGreetingDeserializer`
 * (string-only) in tests that need two candidates of a union type with
 * mutually exclusive data shapes.
 */
class ExampleBagDeserializer extends AbstractDeserializer
{
    public function deserialize(array|string $data, string $class): object
    {
        $data = $this->assertArray($data);

        return ExampleBag::fromArray([
            'name' => $data['name'] . ' (via registry)',
            'amount' => $data['amount'],
        ]);
    }
}
