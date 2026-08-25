<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\BackboneDispatcher\Exception;

/**
 * Exception for a `DeserializerInterface` that only accepts array data (or
 * only string data) receiving the other shape instead.
 *
 * Thrown by `AbstractDeserializer::assertArray()`/`assertString()`.
 * `ObjectFactoryRegistry` catches this one specifically (not
 * `ObjectFactoryException` in general) while trying the explicit candidates
 * of a union type, so a shape mismatch moves on to the next candidate
 * instead of failing the whole union outright — a genuine business
 * exception thrown deeper inside a deserializer's own logic still
 * propagates uncaught, since it is never this exception.
 */
class UnsupportedDataTypeException extends ObjectFactoryException
{
    /**
     * Returns a new exception for a deserializer that received data of the
     * wrong shape.
     *
     * @param string $deserializer The class of the deserializer that
     * rejected the data.
     * @param string $expected The shape it expects instead (`'array'` or
     * `'string'`).
     * @return self
     */
    public static function forDeserializer(string $deserializer, string $expected): self
    {
        return new self([
            '{deserializer} requires {expected} data.',
            'deserializer' => $deserializer,
            'expected' => $expected,
        ]);
    }

    /**
     * Returns a new exception for a deserializer whose required keys (see
     * `AbstractDeserializer::assertKeys()`) are missing from otherwise
     * array-shaped data — e.g. two candidates of a union type both accept
     * an array, but only one of them actually has the keys this
     * deserializer needs.
     *
     * @param string $deserializer The class of the deserializer that
     * rejected the data.
     * @param string[] $keys The missing keys, dot-separated for nested ones
     * (e.g. `'certificate.data'`).
     * @return self
     */
    public static function forMissingKeys(string $deserializer, array $keys): self
    {
        return new self([
            '{deserializer} is missing required key(s): {keys}.',
            'deserializer' => $deserializer,
            'keys' => implode(', ', $keys),
        ]);
    }

    /**
     * Returns a new exception for a deserializer whose JSON Schema (see
     * `AbstractDeserializer::assertSchema()`) rejected the data — the same
     * "two candidates of a union type could both be array-shaped" case
     * `forMissingKeys()` handles, but for structural rules a flat/nested
     * key list cannot express (types, formats, enums, conditionals, etc.).
     *
     * @param string $deserializer The class of the deserializer that
     * rejected the data.
     * @param string[] $errors Formatted validation error messages.
     * @return self
     */
    public static function forSchema(string $deserializer, array $errors): self
    {
        return new self([
            '{deserializer} does not match the expected schema: {errors}.',
            'deserializer' => $deserializer,
            'errors' => implode(' ', $errors),
        ]);
    }
}
