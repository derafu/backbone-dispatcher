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
 * Walks the package registry of the application and returns plain data
 * describing its packages, components, workers and operations.
 */
interface ExplorerInterface
{
    /**
     * Returns the list of packages of the application.
     *
     * @return array
     */
    public function getPackages(): array;

    /**
     * Returns the list of components of a package.
     *
     * @param string $package
     * @return array
     */
    public function getComponents(string $package): array;

    /**
     * Returns the list of workers of a component.
     *
     * @param string $package
     * @param string $component
     * @return array
     */
    public function getWorkers(string $package, string $component): array;

    /**
     * Returns the list of operations of a worker.
     *
     * @param string $package
     * @param string $component
     * @param string $worker
     * @return array
     */
    public function getOperations(
        string $package,
        string $component,
        string $worker
    ): array;

    /**
     * Returns the data of a specific package.
     *
     * @param string $package
     * @param boolean $withComponents
     * @return array
     */
    public function getPackage(
        string $package,
        bool $withComponents = false
    ): array;

    /**
     * Returns the data of a specific component.
     *
     * @param string $package
     * @param string $component
     * @param boolean $withWorkers
     * @return array
     */
    public function getComponent(
        string $package,
        string $component,
        bool $withWorkers = false
    ): array;

    /**
     * Returns the data of a specific worker.
     *
     * @param string $package
     * @param string $component
     * @param string $worker
     * @param boolean $withOperations
     * @return array
     */
    public function getWorker(
        string $package,
        string $component,
        string $worker,
        bool $withOperations = false
    ): array;

    /**
     * Returns the data of a specific operation.
     *
     * @param string $package
     * @param string $component
     * @param string $worker
     * @param string $operation
     * @return array
     */
    public function getOperation(
        string $package,
        string $component,
        string $worker,
        string $operation
    ): array;

    /**
     * Single entry point over the rest of this interface: resolves a
     * discovery id (`"package"`, `"package.component"`,
     * `"package.component.worker"` or `"package.component.worker::operation"`)
     * to whichever of `getPackage()`/`getComponent()`/`getWorker()`/
     * `getOperation()` matches — or, for `null` (there is no single "root"
     * resource to look up), `getPackages()`.
     *
     * @param string|null $id
     * @return array
     */
    public function describe(?string $id = null): array;

    /**
     * Same resolution as `describe()`, but every level nests its children
     * underneath instead of stopping at that one node — a package comes
     * back with its `components`, each of those with its own `workers`,
     * each of those with its own `operations`. An operation id resolves
     * exactly like `describe()`, since operations have no children to nest.
     *
     * @param string|null $id
     * @return array
     */
    public function tree(?string $id = null): array;
}
