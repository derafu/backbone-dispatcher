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
 * A real value object following the `JsonSerializable` convention, used to
 * exercise the default Serializer's `JsonSerializable` path. It nests
 * another `JsonSerializable` value to exercise recursive flattening.
 */
class ExampleGreeting implements JsonSerializable
{
    public function __construct(
        private readonly string $message,
        private readonly ?ExampleGreeting $reply = null,
    ) {
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function jsonSerialize(): array
    {
        return [
            'message' => $this->message,
            'reply' => $this->reply,
        ];
    }
}
