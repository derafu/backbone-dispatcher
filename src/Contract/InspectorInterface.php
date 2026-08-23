<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\BackboneDispatcher\Contract;

/**
 * Reads reflection and PHPDoc data from a class.
 *
 * `getClassDoc()` and `getPublicMethods()` parse PHPDoc for every method —
 * the same, deliberately expensive work every time, safe to cache (e.g.
 * with a decorator backed by a PSR-6 pool) since it only ever depends on
 * the class itself. `isOperation()`, `hasOperationAttribute()` and
 * `getOperationParameters()` are narrow, PHPDoc-free reflection checks used
 * on the dispatch path, on every single call — cheap enough on their own
 * that caching them would not be worth it.
 */
interface InspectorInterface
{
    /**
     * Gets the documentation of a class.
     *
     * @param object $service
     * @return array
     */
    public function getClassDoc(object $service): array;

    /**
     * Gets the operations of a class tagged with `#[Operation]`.
     *
     * @param object $service
     * @return array
     */
    public function getTaggedOperations(object $service): array;

    /**
     * Checks whether `$method` is an operation of `$service`: a public
     * method declared directly on its class (not inherited), whose name
     * does not start with `_`.
     *
     * @param object $service
     * @param string $method
     * @return bool
     */
    public function isOperation(object $service, string $method): bool;

    /**
     * Checks whether `$method` is an operation of `$service` (see
     * `isOperation()`) tagged with the `#[Operation]` attribute.
     *
     * @param object $service
     * @param string $method
     * @return bool
     */
    public function hasOperationAttribute(object $service, string $method): bool;

    /**
     * Gets the parameters of one operation of a worker, by name.
     *
     * @param object $service
     * @param string $method
     * @return array
     */
    public function getOperationParameters(object $service, string $method): array;

    /**
     * Gets the public methods of a class with its parameters.
     *
     * @param object $service
     * @param array $filters
     * @return array
     */
    public function getPublicMethods(object $service, array $filters = []): array;
}
