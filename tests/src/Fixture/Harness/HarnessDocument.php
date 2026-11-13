<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsBackboneDispatcher\Fixture\Harness;

/**
 * A value object that is built from text (`doc:<id>`) and serializes with that
 * text under `raw`: the shape of the documents of the dispatchers whose output
 * can be fed back as input.
 */
class HarnessDocument
{
    public function __construct(
        private readonly string $id,
        private readonly string $raw,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return array{id: string, raw: string, length: int}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'raw' => $this->raw, 'length' => strlen($this->raw)];
    }
}
