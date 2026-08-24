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
 * Wraps `ExplorerInterface` so that no `Throwable` ever crosses back to the
 * caller uncaught: every call either returns a successful
 * `DiscoveryResultInterface` or a failure one, built from whatever was
 * thrown — the same guarantee `SafeDispatcherInterface` gives around
 * dispatching an operation, applied to exploring the package tree instead.
 *
 * Mirrors every public method of `ExplorerInterface` one to one, changing
 * only the return type.
 */
interface SafeExplorerInterface
{
    /**
     * @return DiscoveryResultInterface
     */
    public function getPackages(): DiscoveryResultInterface;

    /**
     * @param string $package
     * @return DiscoveryResultInterface
     */
    public function getComponents(string $package): DiscoveryResultInterface;

    /**
     * @param string $package
     * @param string $component
     * @return DiscoveryResultInterface
     */
    public function getWorkers(string $package, string $component): DiscoveryResultInterface;

    /**
     * @param string $package
     * @param string $component
     * @param string $worker
     * @return DiscoveryResultInterface
     */
    public function getOperations(
        string $package,
        string $component,
        string $worker
    ): DiscoveryResultInterface;

    /**
     * @param string $package
     * @param boolean $withComponents
     * @return DiscoveryResultInterface
     */
    public function getPackage(
        string $package,
        bool $withComponents = false
    ): DiscoveryResultInterface;

    /**
     * @param string $package
     * @param string $component
     * @param boolean $withWorkers
     * @return DiscoveryResultInterface
     */
    public function getComponent(
        string $package,
        string $component,
        bool $withWorkers = false
    ): DiscoveryResultInterface;

    /**
     * @param string $package
     * @param string $component
     * @param string $worker
     * @param boolean $withOperations
     * @return DiscoveryResultInterface
     */
    public function getWorker(
        string $package,
        string $component,
        string $worker,
        bool $withOperations = false
    ): DiscoveryResultInterface;

    /**
     * @param string $package
     * @param string $component
     * @param string $worker
     * @param string $operation
     * @return DiscoveryResultInterface
     */
    public function getOperation(
        string $package,
        string $component,
        string $worker,
        string $operation
    ): DiscoveryResultInterface;

    /**
     * @param string|null $id
     * @return DiscoveryResultInterface
     */
    public function describe(?string $id = null): DiscoveryResultInterface;

    /**
     * @param string|null $id
     * @return DiscoveryResultInterface
     */
    public function tree(?string $id = null): DiscoveryResultInterface;
}
