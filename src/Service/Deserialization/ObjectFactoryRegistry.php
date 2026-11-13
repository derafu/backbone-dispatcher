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
use Derafu\BackboneDispatcher\Contract\TypeAwareDeserializerInterface;
use Derafu\BackboneDispatcher\Exception\NoDeserializerFoundException;
use Derafu\BackboneDispatcher\Exception\ObjectFactoryException;
use Derafu\BackboneDispatcher\Exception\UnsupportedDataTypeException;

/**
 * Resolves which `DeserializerInterface` builds a given class, and
 * delegates to it.
 *
 * Handles the things no individual `DeserializerInterface` has to: `$data`
 * being `null` (returns `null` right away), `$class` being a union type
 * string (`"A|B"`, as produced by `Inspector` for union-typed parameters)
 * — each candidate is tried in order — and the pseudo-type candidate
 * `"array"`, which needs no deserializer at all: it matches as soon as
 * `$data` is already a plain PHP array, returning it unchanged.
 *
 * For each candidate, the explicit map (and the `"array"` passthrough) is
 * tried first: if a deserializer rejects the data's shape
 * (`UnsupportedDataTypeException`, thrown by
 * `AbstractDeserializer::assertArray()`/`assertString()`/`assertKeys()`/
 * `assertSchema()`), the next candidate is tried instead — e.g. for
 * `DocumentBagInterface|XmlDocumentInterface|string`, array data builds a
 * `DocumentBagInterface` but string data skips it and builds an
 * `XmlDocumentInterface` instead. A candidate's deserializer throwing
 * anything else (a genuine business exception from deeper inside it) is
 * not a shape mismatch and propagates immediately, without trying further
 * candidates. If no explicit candidate matches at all, `$fallback` (if
 * given) is tried next, for each remaining class candidate in turn, moving
 * on to the next one if it throws.
 *
 * Every rejection along the way is kept (not just swallowed by `continue`):
 * if every candidate ends up failing, `NoDeserializerFoundException` is
 * built with all of them, so the final error says exactly why each
 * candidate was rejected instead of just "nothing worked".
 */
class ObjectFactoryRegistry implements ObjectFactoryInterface
{
    /** @var array<class-string, DeserializerInterface> */
    private readonly array $deserializers;

    /**
     * @param iterable<class-string, DeserializerInterface> $deserializers Keyed
     * by the exact class/interface name each one builds.
     * @param DeserializerInterface|null $fallback Tried when no entry in
     * `$deserializers` matches, e.g. a `FromArrayDeserializer` for classes
     * that follow that convention without needing explicit registration.
     */
    public function __construct(
        iterable $deserializers = [],
        private readonly ?DeserializerInterface $fallback = null,
    ) {
        $this->deserializers = is_array($deserializers)
            ? $deserializers
            : iterator_to_array($deserializers);
    }

    /**
     * {@inheritDoc}
     */
    public function create(array|string|null $data, string $class): array|object|null
    {
        if ($data === null) {
            return null;
        }

        $candidates = explode('|', $class);
        $rejections = [];

        foreach ($candidates as $candidate) {
            if ($candidate === 'array') {
                if (is_array($data)) {
                    return $data;
                }

                continue;
            }

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

    /**
     * {@inheritDoc}
     *
     * A fallback that is not a `TypeAwareDeserializerInterface` counts as not
     * supporting anything: without data there is no way to know what it can
     * build.
     */
    public function supports(string $class): bool
    {
        foreach (explode('|', $class) as $candidate) {
            if ($candidate === 'array' || isset($this->deserializers[$candidate])) {
                return true;
            }

            if (
                $this->fallback instanceof TypeAwareDeserializerInterface
                && $this->fallback->supports($candidate)
            ) {
                return true;
            }
        }

        return false;
    }
}
