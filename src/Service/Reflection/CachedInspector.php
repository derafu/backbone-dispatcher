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
            fn () => $this->inspector->getClassDoc($service)
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getTaggedOperations(object $service): array
    {
        return $this->getPublicMethods($service, ['operation' => true]);
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
    public function getPublicMethods(object $service, array $filters = []): array
    {
        return $this->remember(
            $this->cacheKey->build('backbone_dispatcher.public_methods', $service::class, $filters ?: null),
            fn () => $this->inspector->getPublicMethods($service, $filters)
        );
    }

    /**
     * Returns the cached value for `$key`, computing and storing it first
     * on a miss. Bypasses the cache pool entirely when `$ttl <= 0`.
     *
     * @param string $key
     * @param callable $compute
     * @return array
     */
    private function remember(string $key, callable $compute): array
    {
        if ($this->ttl <= 0) {
            return $compute();
        }

        $item = $this->cache->getItem($key);

        if ($item->isHit()) {
            return $item->get();
        }

        $value = $compute();

        $item->set($value);
        $item->expiresAfter($this->ttl);
        $this->cache->save($item);

        return $value;
    }
}
