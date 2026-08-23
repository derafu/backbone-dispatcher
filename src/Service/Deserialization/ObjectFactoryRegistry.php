<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\BackboneDispatcher\Service\Deserialization;

use Derafu\BackboneDispatcher\Contract\DeserializerInterface;
use Derafu\BackboneDispatcher\Contract\ObjectFactoryInterface;
use Derafu\BackboneDispatcher\Exception\NoDeserializerFoundException;
use Derafu\BackboneDispatcher\Exception\ObjectFactoryException;

/**
 * Resolves which `DeserializerInterface` builds a given class, and
 * delegates to it.
 *
 * Handles the two things no individual `DeserializerInterface` has to:
 * `$data` being `null` (returns `null` right away), and `$class` being a
 * union type string (`"A|B"`, as produced by `Inspector` for union-typed
 * parameters) — each candidate is tried in order.
 *
 * For each candidate, the explicit map is tried first; if none matches,
 * `$fallback` (if given) is tried instead, for each candidate in turn,
 * moving on to the next one if it throws.
 */
class ObjectFactoryRegistry implements ObjectFactoryInterface
{
    /**
     * @param array<class-string, DeserializerInterface> $deserializers Keyed
     * by the exact class/interface name each one builds.
     * @param DeserializerInterface|null $fallback Tried when no entry in
     * `$deserializers` matches, e.g. a `FromArrayDeserializer` for classes
     * that follow that convention without needing explicit registration.
     */
    public function __construct(
        private readonly array $deserializers = [],
        private readonly ?DeserializerInterface $fallback = null,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function create(array|string|null $data, string $class): ?object
    {
        if ($data === null) {
            return null;
        }

        $candidates = explode('|', $class);

        foreach ($candidates as $candidate) {
            if (isset($this->deserializers[$candidate])) {
                return $this->deserializers[$candidate]->deserialize($data, $candidate);
            }
        }

        if ($this->fallback !== null) {
            foreach ($candidates as $candidate) {
                if (!class_exists($candidate)) {
                    continue;
                }

                try {
                    return $this->fallback->deserialize($data, $candidate);
                } catch (ObjectFactoryException) {
                    continue;
                }
            }
        }

        throw NoDeserializerFoundException::forClass($class);
    }
}
