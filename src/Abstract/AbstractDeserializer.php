<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\BackboneDispatcher\Abstract;

use Derafu\BackboneDispatcher\Contract\DeserializerInterface;
use InvalidArgumentException;

/**
 * Base class for a `DeserializerInterface` that only ever accepts array
 * data or only ever accepts string data (never both), so it asserts that
 * shape as its very first step. Factors out that repeated
 * `is_array()`/`is_string()` check-and-throw.
 */
abstract class AbstractDeserializer implements DeserializerInterface
{
    /**
     * Asserts that `$data` is an array, returning it narrowed to that type.
     *
     * @param array<string, mixed>|string $data
     * @return array<string, mixed>
     */
    protected function assertArray(array|string $data): array
    {
        if (!is_array($data)) {
            throw new InvalidArgumentException(sprintf(
                '%s requires array data.',
                static::class,
            ));
        }

        return $data;
    }

    /**
     * Asserts that `$data` is a string, returning it narrowed to that type.
     *
     * @param array<string, mixed>|string $data
     * @return string
     */
    protected function assertString(array|string $data): string
    {
        if (!is_string($data)) {
            throw new InvalidArgumentException(sprintf(
                '%s requires a string (for example, a base64-encoded string).',
                static::class,
            ));
        }

        return $data;
    }
}
