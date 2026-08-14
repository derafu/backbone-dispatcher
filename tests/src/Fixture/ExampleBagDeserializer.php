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

use Derafu\BackboneDispatcher\Contract\DeserializerInterface;
use InvalidArgumentException;

/**
 * A deliberately distinguishable deserializer for `ExampleBag`: it marks
 * the name, so tests can prove an `ObjectFactoryRegistry` map entry was
 * used instead of `ExampleBag`'s own `fromArray()` (the fallback), even
 * though both could build the same class.
 *
 * `DeserializerInterface` allows `$data` to be a `string` too (e.g. a
 * base64-encoded file for a certificate/CAF deserializer), since that
 * decision belongs to each deserializer, not the interface. This one only
 * makes sense for array data, so it rejects a string explicitly instead of
 * indexing into it.
 */
class ExampleBagDeserializer implements DeserializerInterface
{
    public function deserialize(array|string $data, string $class): object
    {
        if (!is_array($data)) {
            throw new InvalidArgumentException(
                'ExampleBagDeserializer requires array data.'
            );
        }

        return ExampleBag::fromArray([
            'name' => $data['name'] . ' (via registry)',
            'amount' => $data['amount'],
        ]);
    }
}
