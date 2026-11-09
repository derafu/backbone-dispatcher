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

/**
 * A class whose `fromArray()` does not return an instance of itself: it
 * returns whatever the data says under the `result` key.
 */
class MisbehavingFromArray
{
    /** @param array{result: mixed} $data */
    public static function fromArray(array $data): mixed
    {
        return $data['result'];
    }
}
