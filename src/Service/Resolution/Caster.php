<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\BackboneDispatcher\Service\Resolution;

use Derafu\BackboneDispatcher\Contract\ObjectFactoryInterface;

/**
 * Class for handling the mapping and conversion between internal data types of
 * the application in PHP and the generic (transport-agnostic) input/output of
 * the dispatcher.
 */
class Caster
{
    public function __construct(
        private readonly ObjectFactoryInterface $objectFactory,
    ) {
    }

    /**
     * Transforms a value to another according to its data type.
     *
     * @param mixed $value
     * @param string $from
     * @param string $to
     * @return mixed
     */
    public function cast(mixed $value, string $from, string $to): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($from === $to) {
            return $value;
        }

        if ($from === 'string') {

            if ($to === 'float') {
                return (float) $value;
            }

            if ($to === 'int') {
                return (int) $value;
            }

            if ($to === 'bool') {
                return (bool) $value;
            }
        }

        if ($from === 'native') {
            return $value;
        }

        if ($from === 'object') {
            if ($to === 'array' && is_array($value)) {
                return $value;
            }

            return $this->objectFactory->create($value, $to);
        }

        return $value;
    }

    /**
     * Returns the translation of a PHP data type to its OpenAPI/JSON Schema
     * type name(s), for documentation purposes (e.g. backbone-api's
     * Documenter).
     *
     * A union type reports every distinct candidate, not a single guess:
     * `int|string` resolves to `['integer', 'string']`, and
     * `SomeInterface|string` resolves to `['object', 'string']` — the
     * `string` alternative is not dropped just because one of the other
     * candidates is a class/interface. Multiple class/interface candidates
     * collapse into a single `'object'` entry, since the plain `type`
     * keyword cannot distinguish which class beyond that (a `oneOf`/`$ref`
     * schema would be a separate feature).
     *
     * @param string $type
     * @return string|string[] A single type name, or (for a union) the list
     * of every distinct candidate's translated type name.
     */
    public function resolveType(string $type): string|array
    {
        if (str_contains($type, '|')) {
            $names = array_values(array_unique(array_map(
                $this->translateScalarName(...),
                explode('|', $type)
            )));

            return count($names) === 1 ? $names[0] : $names;
        }

        return $this->translateScalarName($type);
    }

    /**
     * Resolves the casting strategy `cast()` should apply for a parameter's
     * declared type.
     *
     * Unlike `resolveType()`, this does not need to report every candidate
     * of a union accurately — it only needs to decide whether `cast()` has
     * to go through `ObjectFactoryInterface` at all. A union is bucketed as
     * `'object'` as soon as any one of its candidates is a class/interface,
     * exactly like a bare class/interface type — the same behavior this
     * method (via the old, single `resolveType()`) has always had, so a
     * type like `SomeInterface|string` keeps being resolved through the
     * object factory (which itself already knows how to fall back to the
     * `string` candidate).
     *
     * The one new outcome is `'native'`: a union where every candidate is a
     * scalar type (`int|string`, say). There, the value already comes out
     * of `json_decode()` (or an equivalent transport) as one of those PHP
     * scalar types, so nothing needs to be cast at all.
     *
     * @param string $type
     * @return string
     */
    public function resolveCastStrategy(string $type): string
    {
        if (str_contains($type, '|')) {
            foreach (explode('|', $type) as $candidate) {
                if ($this->translateScalarName($candidate) === 'object') {
                    return 'object';
                }
            }

            return 'native';
        }

        return $this->translateScalarName($type);
    }

    /**
     * Translates a single (non-union) PHP type name to its OpenAPI/JSON
     * Schema type name, falling back to `'object'` for anything that is not
     * one of the known scalar type names (i.e. a class/interface name).
     *
     * @param string $type
     * @return string
     */
    private function translateScalarName(string $type): string
    {
        return match ($type) {
            'string' => 'string',
            'float' => 'number',
            'int' => 'integer',
            'bool' => 'boolean',
            'array' => 'array',
            'null' => 'null',
            default => 'object',
        };
    }
}
