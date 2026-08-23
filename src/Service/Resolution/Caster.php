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

        if ($from === 'object') {
            if ($to === 'array' && is_array($value)) {
                return $value;
            }

            return $this->objectFactory->create($value, $to);
        }

        return $value;
    }

    /**
     * Returns the translation of a PHP data type to a generic type name.
     *
     * The translation is performed to valid data types in OpenAPI/JSON
     * Schema, since it is also used by transports (e.g. backbone-api) that
     * document the API following that convention.
     *
     * @param string $type
     * @return string
     */
    public function resolveType(string $type): string
    {
        return match ($type) {
            'string' => 'string',
            'float' => 'number',
            'int' => 'integer',
            'bool' => 'boolean',
            'array' => 'array',
            default => 'object',
        };
    }
}
