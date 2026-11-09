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

use BackedEnum;
use Derafu\BackboneDispatcher\Contract\ObjectFactoryInterface;
use Derafu\BackboneDispatcher\Exception\InvalidEnumValueException;
use Derafu\BackboneDispatcher\Exception\InvalidParameterTypeException;
use Derafu\Value\Exception\ValueException;
use Derafu\Value\ValueType;
use ReflectionEnum;

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

        if ($from === 'string' && !is_string($value)) {
            throw InvalidParameterTypeException::forParameter('value', 'string');
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

        if ($from === 'array_of_object') {
            if (!is_array($value)) {
                throw InvalidParameterTypeException::forParameter('value', 'array');
            }
            $elementClass = substr($to, 0, -2); // Strip the trailing '[]'.

            return array_map(
                fn (mixed $item): mixed => $this->objectFactory->create($this->requireObjectData($item), $elementClass),
                $value
            );
        }

        if ($from === 'enum') {
            return $this->castEnum($value, $to);
        }

        if ($from === 'object') {
            if ($to === 'array' && is_array($value)) {
                return $value;
            }

            return $this->objectFactory->create($this->requireObjectData($value), $to);
        }

        return $value;
    }

    /**
     * Casts a value to a case of a backed enum.
     *
     * The value must be exactly the backing value of a case (a string for a
     * string-backed enum, an integer for an int-backed enum): no scalar is
     * converted into the other.
     *
     * @param mixed $value
     * @param string $class The class of the backed enum.
     * @return BackedEnum
     * @throws InvalidEnumValueException If the value is not a value of the enum.
     * @throws InvalidParameterTypeException If the class is not a backed enum.
     */
    private function castEnum(mixed $value, string $class): BackedEnum
    {
        if (!is_a($class, BackedEnum::class, true)) {
            throw InvalidParameterTypeException::forParameter('value', $class);
        }

        try {
            return ValueType::backedEnum($class)->parse($value);
        } catch (ValueException $exception) {
            throw InvalidEnumValueException::forEnum(
                $class,
                $value,
                array_map(
                    static fn (BackedEnum $case): int|string => $case->value,
                    $class::cases()
                ),
                $exception
            );
        }
    }

    /**
     * Gets the type of the backing values of a backed enum.
     *
     * @param string $type A type name.
     * @return string|null `'int'` or `'string'` if the type is a backed enum,
     * or `null` if it is not.
     */
    private function getBackedEnumType(string $type): ?string
    {
        if (!enum_exists($type) || !is_a($type, BackedEnum::class, true)) {
            return null;
        }

        return (new ReflectionEnum($type))->getBackingType()?->getName();
    }

    /** @return array<string, mixed>|string|null */
    private function requireObjectData(mixed $value): array|string|null
    {
        if ($value === null || is_string($value)) {
            return $value;
        }
        if (!is_array($value)) {
            throw InvalidParameterTypeException::forParameter('value', 'array<string, mixed>|string|null');
        }

        $data = [];
        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                throw InvalidParameterTypeException::forParameter('value', 'array<string, mixed>|string|null');
            }
            $data[$key] = $item;
        }

        return $data;
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
     * A backed enum (a single type, not a union) is translated to the type of
     * its backing values: `'string'` or `'integer'`.
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

        $enumType = $this->getBackedEnumType($type);
        if ($enumType !== null) {
            return $enumType === 'int' ? 'integer' : 'string';
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
     * A `'native'` outcome is also possible: a union where every candidate is a
     * scalar type (`int|string`, say). There, the value already comes out
     * of `json_decode()` (or an equivalent transport) as one of those PHP
     * scalar types, so nothing needs to be cast at all.
     *
     * A backed enum (a single type, not a union) resolves to its own `'enum'`
     * strategy: its value is the backing value of one of its cases, and
     * `cast()` converts it to that case. Any other enum, and a union that has an
     * enum among its candidates, is bucketed as `'object'` like any other
     * class.
     *
     * A `'SomeClass[]'` type (an `array` parameter whose PHPDoc `@param`
     * documented its element class, see `Inspector::resolveArrayElementType()`)
     * resolves to its own `'array_of_object'` strategy, never `'object'` —
     * `cast()` needs to loop the value and hydrate each element, not hand
     * the whole array to `ObjectFactoryInterface` as if it were a single
     * object.
     *
     * @param string $type
     * @return string
     */
    public function resolveCastStrategy(string $type): string
    {
        if (str_ends_with($type, '[]')) {
            return 'array_of_object';
        }

        if (str_contains($type, '|')) {
            foreach (explode('|', $type) as $candidate) {
                if ($this->translateScalarName($candidate) === 'object') {
                    return 'object';
                }
            }

            return 'native';
        }

        if ($this->getBackedEnumType($type) !== null) {
            return 'enum';
        }

        return $this->translateScalarName($type);
    }

    /**
     * Translates a single (non-union) PHP type name to its OpenAPI/JSON
     * Schema type name, falling back to `'object'` for anything that is not
     * one of the known scalar type names (i.e. a class/interface name).
     *
     * `'SomeClass[]'` (see `resolveCastStrategy()`) translates to plain
     * `'array'` here — for documentation purposes (this method's only
     * other caller, `resolveType()`) the runtime shape is still a JSON
     * array, regardless of whether `cast()` also knows to hydrate each of
     * its elements.
     *
     * @param string $type
     * @return string
     */
    private function translateScalarName(string $type): string
    {
        if (str_ends_with($type, '[]')) {
            return 'array';
        }

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
