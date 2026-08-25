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
use Derafu\BackboneDispatcher\Exception\UnsupportedDataTypeException;

/**
 * Resolves which `DeserializerInterface` builds a given class, and
 * delegates to it.
 *
 * Handles the two things no individual `DeserializerInterface` has to:
 * `$data` being `null` (returns `null` right away), and `$class` being a
 * union type string (`"A|B"`, as produced by `Inspector` for union-typed
 * parameters) — each candidate is tried in order.
 *
 * For each candidate, the explicit map is tried first: if its deserializer
 * rejects the data's shape (`UnsupportedDataTypeException`, thrown by
 * `AbstractDeserializer::assertArray()`/`assertString()`/`assertKeys()`/
 * `assertSchema()`), the next candidate is tried instead — e.g. for
 * `DocumentBagInterface|XmlDocumentInterface|string`, array data builds a
 * `DocumentBagInterface` but string data skips it and builds an
 * `XmlDocumentInterface` instead. A candidate's deserializer throwing
 * anything else (a genuine business exception from deeper inside it) is
 * not a shape mismatch and propagates immediately, without trying further
 * candidates. If no explicit candidate matches at all, `$fallback` (if
 * given) is tried next, for each candidate in turn, moving on to the next
 * one if it throws.
 *
 * Every rejection along the way is kept (not just swallowed by `continue`):
 * if every candidate ends up failing, `NoDeserializerFoundException` is
 * built with all of them, so the final error says exactly why each
 * candidate was rejected instead of just "nothing worked".
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
        $rejections = [];

        foreach ($candidates as $candidate) {
            if (isset($this->deserializers[$candidate])) {
                try {
                    return $this->deserializers[$candidate]->deserialize($data, $candidate);
                } catch (UnsupportedDataTypeException $e) {
                    $rejections[$candidate] ??= $e;
                    continue;
                }
            }
        }

        if ($this->fallback !== null) {
            foreach ($candidates as $candidate) {
                if (!class_exists($candidate)) {
                    continue;
                }

                try {
                    return $this->fallback->deserialize($data, $candidate);
                } catch (ObjectFactoryException $e) {
                    $rejections[$candidate] ??= $e;
                    continue;
                }
            }
        }

        throw NoDeserializerFoundException::forClass($class, $rejections);
    }
}
