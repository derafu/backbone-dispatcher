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

use Derafu\BackboneDispatcher\Abstract\AbstractDeserializer;
use Derafu\BackboneDispatcher\Exception\UnsupportedDataTypeException;

/**
 * Builds a `HarnessDocument` from the text `doc:<id>`.
 */
class HarnessDocumentDeserializer extends AbstractDeserializer
{
    /**
     * {@inheritDoc}
     */
    public function deserialize(array|string $data, string $class): object
    {
        $text = $this->assertString($data);

        if (!str_starts_with($text, 'doc:') || strlen($text) === 4) {
            throw UnsupportedDataTypeException::forDeserializer(static::class, 'string like doc:<id>');
        }

        return new HarnessDocument(substr($text, 4), $text);
    }
}
