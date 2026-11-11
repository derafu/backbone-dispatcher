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
 * the class itself. `getOperationMethod()`, `isOperation()` and
 * `hasOperationAttribute()` resolve external names without parsing PHPDoc.
 * `getOperationParameters()` reads the resolved method's parameters,
 * parsing PHPDoc only when required by an array parameter. These dispatch
 * checks are forwarded directly by the cache decorator.
 */
interface InspectorInterface
{
    /**
     * Resolves an external operation name to its PHP method name.
     *
     * An explicit Operation name is the only external name of that method.
     * Without one, the PHP method name is used. Returns null when the
     * operation does not exist. Duplicate external names are rejected.
     *
     * @param object $service
     * @param string $operation
     * @return string|null
     */
    public function getOperationMethod(object $service, string $operation): ?string;

    /**
     * Gets the documentation of a class.
     *
     * @param object $service
     * @return array{
     *     name: class-string, summary: string|null, description: string|null,
     *     tags: array<string, list<string>>, links: list<array{url: string, description: string|null}>
     * }
     */
    public function getClassDoc(object $service): array;

    /**
     * Checks whether `$method` is an external operation name of `$service`.
     * It resolves to a public PHP method whose name does not start with `_`.
     *
     * A purely technical existence check, never a judgment on whether the
     * method is meant to be exposed as a business operation — that is
     * exclusively an `OperationPolicyInterface`'s call. See `Inspector`'s
     * own docblock for why.
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
     * Gets parameters by the resolved PHP method name, not the external name.
     *
     * @param object $service
     * @param string $method
     * @return list<array{name: string, type: string, required: bool, default: mixed, description: string|null}>
     */
    public function getOperationParameters(object $service, string $method): array;

    /**
     * Gets public operation metadata indexed by external operation name.
     *
     * Never filters by `#[Operation]` or anything else: deciding which of
     * these public methods counts as an exposed operation is exclusively
     * an `OperationPolicyInterface`'s call, made by the caller (see
     * `Explorer`), never by `Inspector` itself.
     *
     * @param object $service
     * @return array<string, array{
     *     name: string, summary: string|null, description: string|null,
     *     parameters: list<array{name: string, type: string, required: bool, default: mixed, description: string|null, example?: mixed}>,
     *     returns: array{type: string, description: string|null},
     *     throws: array<array{type: string, description: string|null}>,
     *     tags: array<string, list<string>>, links: list<array{url: string, description: string|null}>,
     *     deprecated: bool,
     *     operation: array{name: string|null, summary: string|null, description: string|null, parameters: array<array-key, mixed>, results: array<array-key, mixed>}|null
     * }>
     */
    public function getPublicMethods(object $service): array;
}
