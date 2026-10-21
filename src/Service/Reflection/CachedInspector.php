<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\BackboneDispatcher\Service\Reflection;

use Derafu\BackboneDispatcher\Contract\InspectorInterface;
use Derafu\Cache\CacheKey;
use Derafu\Cache\Contract\CacheKeyInterface;
use Derafu\Translation\Exception\Core\TranslatableLogicException as LogicException;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Caches `getClassDoc()` and `getPublicMethods()` behind a PSR-6 pool — the
 * two calls that parse PHPDoc for every method of a class, the same result
 * every time for a given class, until the next deploy.
 *
 * `isOperation()`, `hasOperationAttribute()` and `getOperationParameters()`
 * are deliberately left uncached, passed straight through to the wrapped
 * `InspectorInterface`: they never parse PHPDoc, they already run on every
 * dispatch, and reading them through a cache backend (e.g. a network round
 * trip to Redis) could easily cost more than the reflection they would
 * replace.
 *
 * No invalidation logic lives here on purpose: whether an entry outlives a
 * deploy is entirely a matter of which PSR-6 pool gets injected (an
 * in-memory pool dies with the process; a shared one is the caller's to
 * flush, or not, as part of their own deploy).
 *
 * There is no default cache pool: `$cache` must be provided explicitly
 * (e.g. `derafu/cache`'s `PhpFilesCache`/`FilesystemCache`, or any other
 * PSR-6 `CacheItemPoolInterface`). If caching isn't wanted at all, simply
 * don't use `CachedInspector` — inject the plain `Inspector` wherever
 * `InspectorInterface` is expected instead. That keeps this class free of
 * an internal "am I on or off" branch: it always has a real pool to talk
 * to.
 *
 * Set `$ttl` to `0` (or less) to skip the pool on every call without
 * swapping which `InspectorInterface` is injected — useful when the choice
 * comes from runtime configuration rather than wiring.
 */
class CachedInspector implements InspectorInterface
{
    public function __construct(
        private readonly InspectorInterface $inspector,
        private readonly CacheItemPoolInterface $cache,
        private readonly int $ttl = 3600,
        private readonly CacheKeyInterface $cacheKey = new CacheKey(),
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function getClassDoc(object $service): array
    {
        return $this->remember(
            $this->cacheKey->build('backbone_dispatcher.class_doc', $service::class),
            fn () => $this->inspector->getClassDoc($service),
            $this->validateClassDoc(...)
        );
    }

    /**
     * {@inheritDoc}
     */
    public function isOperation(object $service, string $method): bool
    {
        return $this->inspector->isOperation($service, $method);
    }

    /**
     * {@inheritDoc}
     */
    public function hasOperationAttribute(object $service, string $method): bool
    {
        return $this->inspector->hasOperationAttribute($service, $method);
    }

    /**
     * {@inheritDoc}
     */
    public function getOperationParameters(object $service, string $method): array
    {
        return $this->inspector->getOperationParameters($service, $method);
    }

    /**
     * {@inheritDoc}
     */
    public function getPublicMethods(object $service): array
    {
        return $this->remember(
            $this->cacheKey->build('backbone_dispatcher.public_methods', $service::class),
            fn () => $this->inspector->getPublicMethods($service),
            $this->validatePublicMethods(...)
        );
    }

    /**
     * Returns the cached value for `$key`, computing and storing it first
     * on a miss. Bypasses the cache pool entirely when `$ttl <= 0`.
     *
     * @template T of array<array-key, mixed>
     * @param string $key
     * @param callable(): T $compute
     * @param callable(array<array-key, mixed>): T $validate
     * @return T
     */
    private function remember(string $key, callable $compute, callable $validate): array
    {
        if ($this->ttl <= 0) {
            return $compute();
        }

        $item = $this->cache->getItem($key);

        if ($item->isHit()) {
            $value = $item->get();
            if (!is_array($value)) {
                throw new LogicException('Cached inspector data must be an array.');
            }

            return $validate($value);
        }

        $value = $compute();

        $item->set($value);
        $item->expiresAfter($this->ttl);
        $this->cache->save($item);

        return $value;
    }

    /**
     * @param array<array-key, mixed> $value
     * @return array{
     *     name: class-string, summary: string|null, description: string|null,
     *     tags: array<string, list<string>>, links: list<array{url: string, description: string|null}>
     * }
     */
    private function validateClassDoc(array $value): array
    {
        $value = $this->validateDocumentation($value);
        $name = $value['name'];
        if (!class_exists($name) && !interface_exists($name) && !trait_exists($name)) {
            throw new LogicException('Cached inspector data has an invalid structure.');
        }
        $value['name'] = $name;

        return $value;
    }

    /**
     * @param array<array-key, mixed> $value
     * @return array<string, array{
     *     name: string, summary: string|null, description: string|null,
     *     parameters: list<array{name: string, type: string, required: bool, default: mixed, description: string|null, example?: mixed}>,
     *     returns: array{type: string, description: string|null},
     *     throws: array<array{type: string, description: string|null}>,
     *     tags: array<string, list<string>>, links: list<array{url: string, description: string|null}>,
     *     deprecated: bool,
     *     operation: array{name: string|null, description: string|null, parameters: array<array-key, mixed>, results: array<array-key, mixed>}|null
     * }>
     */
    private function validatePublicMethods(array $value): array
    {
        $methods = [];
        foreach ($value as $name => $method) {
            if (!is_string($name) || !is_array($method)) {
                throw new LogicException('Cached inspector data has an invalid structure.');
            }
            $method = array_replace($method, $this->validateDocumentation($method));
            $method['parameters'] = $this->validateParameters($this->requireArray($method, 'parameters'));
            $method['returns'] = $this->validateTypeDescription($this->requireArray($method, 'returns'));
            $throws = [];
            foreach ($this->requireArray($method, 'throws') as $key => $throw) {
                if (!is_array($throw)) {
                    throw new LogicException('Cached inspector data has an invalid structure.');
                }
                $throws[$key] = $this->validateTypeDescription($throw);
            }
            $method['throws'] = $throws;
            if (!isset($method['deprecated']) || !is_bool($method['deprecated']) || !array_key_exists('operation', $method)) {
                throw new LogicException('Cached inspector data has an invalid structure.');
            }
            if ($method['operation'] !== null) {
                $operation = $this->requireArray($method, 'operation');
                $operation['name'] = $this->requireNullableString($operation, 'name');
                $operation['description'] = $this->requireNullableString($operation, 'description');
                $operation['parameters'] = $this->requireArray($operation, 'parameters');
                $operation['results'] = $this->requireArray($operation, 'results');
                $method['operation'] = $operation;
            }
            $methods[$name] = $method;
        }

        return $methods;
    }

    /**
     * @param array<array-key, mixed> $value
     * @return array{
     *     name: string, summary: string|null, description: string|null,
     *     tags: array<string, list<string>>, links: list<array{url: string, description: string|null}>
     * }
     */
    private function validateDocumentation(array $value): array
    {
        if (!isset($value['name']) || !is_string($value['name'])) {
            throw new LogicException('Cached inspector data has an invalid structure.');
        }
        $value['summary'] = $this->requireNullableString($value, 'summary');
        $value['description'] = $this->requireNullableString($value, 'description');
        $tags = [];
        foreach ($this->requireArray($value, 'tags') as $name => $entries) {
            if (!is_string($name) || !is_array($entries) || !array_is_list($entries)) {
                throw new LogicException('Cached inspector data has an invalid structure.');
            }
            $strings = [];
            foreach ($entries as $entry) {
                if (!is_string($entry)) {
                    throw new LogicException('Cached inspector data has an invalid structure.');
                }
                $strings[] = $entry;
            }
            $tags[$name] = $strings;
        }
        $value['tags'] = $tags;
        $links = $this->requireArray($value, 'links');
        if (!array_is_list($links)) {
            throw new LogicException('Cached inspector data has an invalid structure.');
        }
        $validatedLinks = [];
        foreach ($links as $link) {
            if (!is_array($link) || !isset($link['url']) || !is_string($link['url'])) {
                throw new LogicException('Cached inspector data has an invalid structure.');
            }
            $link['description'] = $this->requireNullableString($link, 'description');
            $validatedLinks[] = $link;
        }
        $value['links'] = $validatedLinks;

        return $value;
    }

    /**
     * @param array<array-key, mixed> $value
     * @return list<array{name: string, type: string, required: bool, default: mixed, description: string|null, example?: mixed}>
     */
    private function validateParameters(array $value): array
    {
        if (!array_is_list($value)) {
            throw new LogicException('Cached inspector data has an invalid structure.');
        }
        $parameters = [];
        foreach ($value as $parameter) {
            if (!is_array($parameter)
                || !isset($parameter['name'], $parameter['type'], $parameter['required'])
                || !is_string($parameter['name'])
                || !is_string($parameter['type'])
                || !is_bool($parameter['required'])
                || !array_key_exists('default', $parameter)
            ) {
                throw new LogicException('Cached inspector data has an invalid structure.');
            }
            $parameter['description'] = $this->requireNullableString($parameter, 'description');
            $parameters[] = $parameter;
        }

        return $parameters;
    }

    /**
     * @param array<array-key, mixed> $value
     * @return array{type: string, description: string|null}
     */
    private function validateTypeDescription(array $value): array
    {
        if (!isset($value['type']) || !is_string($value['type'])) {
            throw new LogicException('Cached inspector data has an invalid structure.');
        }
        $value['description'] = $this->requireNullableString($value, 'description');

        return $value;
    }

    /**
     * @param array<array-key, mixed> $value
     * @param string $key
     * @return array<array-key, mixed>
     */
    private function requireArray(array $value, string $key): array
    {
        if (!isset($value[$key]) || !is_array($value[$key])) {
            throw new LogicException('Cached inspector data has an invalid structure.');
        }

        return $value[$key];
    }

    /**
     * @param array<array-key, mixed> $value
     * @param string $key
     * @return string|null
     */
    private function requireNullableString(array $value, string $key): ?string
    {
        if (!array_key_exists($key, $value) || ($value[$key] !== null && !is_string($value[$key]))) {
            throw new LogicException('Cached inspector data has an invalid structure.');
        }

        return $value[$key];
    }
}
