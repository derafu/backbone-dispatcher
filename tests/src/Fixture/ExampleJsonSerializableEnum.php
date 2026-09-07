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

use JsonSerializable;

/**
 * A backed enum that opts into its own serialization, used to prove it takes
 * precedence over the generic `name`/`value` enum flattening.
 */
enum ExampleJsonSerializableEnum: string implements JsonSerializable
{
    case Active = 'active';

    public function jsonSerialize(): array
    {
        return ['label' => 'Custom label'];
    }
}
