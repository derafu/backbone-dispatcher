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
 * A string-only deserializer for `ExampleGreeting`, paired with
 * `ExampleBagDeserializer` (array-only) in tests that need two candidates
 * of a union type with mutually exclusive data shapes.
 */
class ExampleGreetingDeserializer extends AbstractDeserializer
{
    public function deserialize(array|string $data, string $class): object
    {
        $data = $this->assertString($data);

        return new ExampleGreeting($data);
    }
}
