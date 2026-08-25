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
use Derafu\BackboneDispatcher\Exception\MissingOptionalDependencyException;
use Derafu\BackboneDispatcher\Exception\UnsupportedDataTypeException;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Helper;
use Opis\JsonSchema\Validator;

/**
 * Base class for a `DeserializerInterface` that only ever accepts array
 * data or only ever accepts string data (never both), so it asserts that
 * shape as its very first step. Factors out that repeated
 * `is_array()`/`is_string()` check-and-throw, plus two ways to go further
 * once the shape is already known to be an array: `assertKeys()` (specific
 * keys must be present, optionally nested) and `assertSchema()` (a full
 * JSON Schema, for rules a key list cannot express — types, formats,
 * enums, conditional requirements).
 *
 * Every assertion here throws `UnsupportedDataTypeException` (not a generic
 * `InvalidArgumentException`) on purpose: `ObjectFactoryRegistry` catches
 * that specific type while trying the candidates of a union type, so a
 * mismatch here moves on to the next candidate instead of failing the
 * whole union outright. This matters beyond the plain array-vs-string case:
 * two candidates of a union type can both be array-shaped (e.g. two
 * different "bag" classes), and only their own required keys/schema tell
 * them apart — `assertArray()` alone cannot.
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
            throw UnsupportedDataTypeException::forDeserializer(static::class, 'array');
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
            throw UnsupportedDataTypeException::forDeserializer(static::class, 'string');
        }

        return $data;
    }

    /**
     * Asserts that `$data` (already known to be an array, e.g. via
     * `assertArray()`) has every key `$requiredKeys` names, returning
     * `$data` unchanged.
     *
     * `$requiredKeys` is normally a flat list of key names:
     *
     *   `['tipo', 'caratula', 'detalle']`
     *
     * A key can instead map to its own list (or nested map) of keys
     * required *inside* it, to check more than one level at once:
     *
     *   `['tipo', 'certificate' => ['data', 'password']]`
     *
     * — this requires `data['tipo']` to exist, `data['certificate']` to
     * exist and be an array, and that array to have both `data` and
     * `password`. Nesting is not limited to one level.
     *
     * A key existing with a `null` value still counts as present —
     * `array_key_exists()`, not `isset()` — since "required" is about
     * shape, not about a specific key never being legitimately `null`.
     *
     * @param array<string, mixed> $data
     * @param array<int|string, mixed> $requiredKeys
     * @return array<string, mixed>
     */
    protected function assertKeys(array $data, array $requiredKeys): array
    {
        $missing = $this->collectMissingKeys($data, $requiredKeys);

        if ($missing !== []) {
            throw UnsupportedDataTypeException::forMissingKeys(static::class, $missing);
        }

        return $data;
    }

    /**
     * Asserts that `$data` matches a JSON Schema, returning `$data`
     * unchanged.
     *
     * For structural rules `assertKeys()` cannot express — types, formats,
     * enums, conditional requirements, nested item schemas, etc. Needs
     * `opis/json-schema` (a `require-dev`/`suggest`, not a hard
     * dependency of this package — most deserializers never call this
     * method, so most consumers never need to install it).
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $schema A JSON Schema document.
     * @return array<string, mixed>
     */
    protected function assertSchema(array $data, array $schema): array
    {
        if (!class_exists(Validator::class)) {
            throw MissingOptionalDependencyException::forFeature(
                static::class . '::assertSchema()',
                'opis/json-schema',
            );
        }

        $validator = new Validator(max_errors: 20, stop_at_first_error: false);

        $result = $validator->validate(Helper::toJSON($data), Helper::toJSON($schema));

        if ($result->hasError()) {
            $messages = [];
            foreach ((new ErrorFormatter())->format($result->error()) as $pointer => $pointerMessages) {
                foreach ($pointerMessages as $message) {
                    $messages[] = $pointer === '' ? $message : "{$pointer}: {$message}";
                }
            }

            throw UnsupportedDataTypeException::forSchema(static::class, $messages);
        }

        return $data;
    }

    /**
     * Recursively collects the dot-separated paths of every key
     * `$requiredKeys` names that is missing from `$data`, walking into
     * nested key requirements as it goes.
     *
     * @param array<string, mixed> $data
     * @param array<int|string, mixed> $requiredKeys
     * @param string $prefix The dot-separated path to `$data` itself,
     * empty at the top level.
     * @return string[]
     */
    private function collectMissingKeys(
        array $data,
        array $requiredKeys,
        string $prefix = '',
    ): array {
        $missing = [];

        foreach ($requiredKeys as $key => $value) {
            // Flat entry (`['tipo', 'caratula']`): the value is itself the
            // required key name, with no nested requirements of its own.
            if (is_int($key)) {
                $path = $prefix === '' ? (string) $value : "{$prefix}.{$value}";

                if (!array_key_exists($value, $data)) {
                    $missing[] = $path;
                }

                continue;
            }

            // Nested entry (`['certificate' => ['data', 'password']]`):
            // the key itself is required, and its value is required to be
            // an array containing everything $value requires in turn.
            $path = $prefix === '' ? $key : "{$prefix}.{$key}";

            if (!array_key_exists($key, $data) || !is_array($data[$key])) {
                $missing[] = $path;

                continue;
            }

            array_push(
                $missing,
                ...$this->collectMissingKeys($data[$key], $value, $path)
            );
        }

        return $missing;
    }
}
