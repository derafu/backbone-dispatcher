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

use Throwable;

/**
 * Exception for a parameter of a backed enum type whose value is not one of the
 * values of the enum.
 */
class InvalidEnumValueException extends ResolverException
{
    /**
     * Maximum length of the received value shown in the message, so a large
     * value sent by the caller does not end up in the error.
     */
    private const MAX_GIVEN_LENGTH = 100;

    /**
     * Returns a new exception for a value that is not valid for a backed enum.
     *
     * A string or an integer is shown as received (limited in length); any
     * other value is shown by its type.
     *
     * @param string $enum The class of the backed enum.
     * @param mixed $value The value that was received.
     * @param list<int|string> $allowed The values of the enum.
     * @param Throwable|null $previous The error that rejected the value.
     * @return self
     */
    public static function forEnum(
        string $enum,
        mixed $value,
        array $allowed,
        ?Throwable $previous = null
    ): self {
        return new self([
            'The value {given} is not valid for the enum {enum}. The allowed values are: {values}.',
            'given' => is_int($value) || is_string($value)
                ? mb_strimwidth(self::encode($value), 0, self::MAX_GIVEN_LENGTH, '...')
                : get_debug_type($value),
            'enum' => $enum,
            'values' => implode(', ', array_map(self::encode(...), $allowed)),
        ], previous: $previous);
    }

    /**
     * Encodes a string or an integer as JSON, so a string is shown between
     * quotes and an integer without them.
     */
    private static function encode(int|string $value): string
    {
        return json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
        );
    }
}
