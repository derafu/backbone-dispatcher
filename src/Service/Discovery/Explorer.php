<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\BackboneDispatcher\Service\Discovery;

use Derafu\Backbone\Contract\PackageRegistryInterface;
use Derafu\BackboneDispatcher\Contract\ExplorerInterface;
use Derafu\BackboneDispatcher\Contract\InspectorInterface;
use Derafu\BackboneDispatcher\Contract\OperationPolicyInterface;
use Derafu\BackboneDispatcher\Exception\InvalidDiscoveryIdException;
use Derafu\BackboneDispatcher\Exception\OperationNotAllowedException;
use Derafu\BackboneDispatcher\Exception\OperationNotFoundException;

/**
 * Walks the package registry of the application and returns plain data
 * describing its packages, components, workers and operations.
 *
 * Transport-agnostic: it does not add links, URLs or any other concept tied
 * to a specific transport (e.g. HATEOAS `_links` for HTTP). Transports that
 * need that can decorate the arrays returned here.
 *
 * When given an `OperationPolicyInterface`, every listing method only shows
 * what it allows, all the way up the tree: `getOperations()` drops
 * operations it rejects, and `getWorkers()`/`getComponents()`/`getPackages()`
 * drop any branch left with zero visible operations underneath — a worker
 * nobody may call is not a worker worth documenting, so it (and, in turn,
 * an empty component or package) simply does not appear. Without a policy,
 * nothing is pruned, matching the default `AllowAllOperationPolicy`
 * behavior. `getOperation()` (a direct lookup, not a listing) throws
 * instead of omitting: `OperationNotFoundException` if it does not exist,
 * `OperationNotAllowedException` if the policy rejects it.
 *
 * Ids are dot-separated for the hierarchy (`"package"`,
 * `"package.component"`, `"package.component.worker"`) and `::`-separated
 * for an operation (`"package.component.worker::operation"`) — the same
 * shape `OperationRequest`'s invocation id uses, so a discovery id with an
 * operation and an invocation id are interchangeable strings.
 */
class Explorer implements ExplorerInterface
{
    public function __construct(
        private PackageRegistryInterface $packageRegistry,
        private InspectorInterface $inspector,
        private ?OperationPolicyInterface $operationPolicy = null,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function getPackages(): array
    {
        $packages = array_keys($this->packageRegistry->getPackages());

        if ($this->operationPolicy !== null) {
            $packages = array_values(array_filter(
                $packages,
                fn ($package) => $this->getComponents($package) !== []
            ));
        }

        return array_map(
            fn ($package) => $this->getPackage($package),
            $packages
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getComponents(string $package): array
    {
        $components = array_keys(
            $this->packageRegistry
            ->getPackage($package)
            ->getComponents()
        );

        if ($this->operationPolicy !== null) {
            $components = array_values(array_filter(
                $components,
                fn ($component) => $this->getWorkers($package, $component) !== []
            ));
        }

        return array_map(
            fn ($component) => $this->getComponent($package, $component),
            $components
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getWorkers(string $package, string $component): array
    {
        $workers = array_keys(
            $this->packageRegistry
            ->getPackage($package)
            ->getComponent($component)
            ->getWorkers()
        );

        if ($this->operationPolicy !== null) {
            $workers = array_values(array_filter(
                $workers,
                fn ($worker) => $this->getOperations($package, $component, $worker) !== []
            ));
        }

        return array_map(
            fn ($worker) => $this->getWorker($package, $component, $worker),
            $workers
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getOperations(
        string $package,
        string $component,
        string $worker
    ): array {
        $workerInstance =
            $this->packageRegistry
            ->getPackage($package)
            ->getComponent($component)
            ->getWorker($worker)
        ;

        $methods = $this->inspector->getPublicMethods($workerInstance);

        $operations = [];
        foreach ($methods as $operation => $info) {
            if (
                $this->operationPolicy !== null
                && !$this->operationPolicy->isAllowed($package, $component, $worker, $operation)
            ) {
                continue;
            }

            $operations[] = $this->buildOperation($package, $component, $worker, $operation, $info);
        }

        return $operations;
    }

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
    ): array {
        $packageInstance = $this->packageRegistry->getPackage($package);

        $data = [
            'id' => $package,
            'name' => $packageInstance->getName(),
            'description' => $packageInstance->getDescription(),
        ];

        if ($withComponents) {
            $data['components'] = $this->getComponents($package);
        }

        return $data;
    }

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
    ): array {
        $componentInstance =
            $this->packageRegistry
            ->getPackage($package)
            ->getComponent($component)
        ;

        $data = [
            'id' => sprintf(
                '%s.%s',
                $package,
                $component
            ),
            'name' => $componentInstance->getName(),
            'description' => $componentInstance->getDescription(),
        ];

        if ($withWorkers) {
            $data['workers'] = $this->getWorkers($package, $component);
        }

        return $data;
    }

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
    ): array {
        $workerInstance =
            $this->packageRegistry
            ->getPackage($package)
            ->getComponent($component)
            ->getWorker($worker)
        ;

        $data = [
            'id' => sprintf(
                '%s.%s.%s',
                $package,
                $component,
                $worker
            ),
            'name' => $workerInstance->getName(),
            'description' => $workerInstance->getDescription(),
        ];

        if ($withOperations) {
            $data['operations'] = $this->getOperations($package, $component, $worker);
        }

        return $data;
    }

    /**
     * {@inheritDoc}
     */
    public function getOperation(
        string $package,
        string $component,
        string $worker,
        string $operation
    ): array {
        $workerInstance =
            $this->packageRegistry
            ->getPackage($package)
            ->getComponent($component)
            ->getWorker($worker)
        ;

        $methods = $this->inspector->getPublicMethods($workerInstance);

        if (!isset($methods[$operation])) {
            throw OperationNotFoundException::forOperation(
                $package,
                $component,
                $worker,
                $operation
            );
        }

        if (
            $this->operationPolicy !== null
            && !$this->operationPolicy->isAllowed($package, $component, $worker, $operation)
        ) {
            throw OperationNotAllowedException::forOperation(
                $package,
                $component,
                $worker,
                $operation
            );
        }

        return $this->buildOperation($package, $component, $worker, $operation, $methods[$operation]);
    }

    /**
     * {@inheritDoc}
     */
    public function describe(?string $id = null): array
    {
        if ($id === null || $id === '') {
            return $this->getPackages();
        }

        [$segments, $operation] = $this->parseDiscoveryId($id);

        if ($operation !== null) {
            return $this->getOperation($segments[0], $segments[1], $segments[2], $operation);
        }

        return match (count($segments)) {
            1 => $this->getPackage($segments[0]),
            2 => $this->getComponent($segments[0], $segments[1]),
            3 => $this->getWorker($segments[0], $segments[1], $segments[2]),
            default => throw InvalidDiscoveryIdException::forId($id),
        };
    }

    /**
     * {@inheritDoc}
     */
    public function tree(?string $id = null): array
    {
        if ($id === null || $id === '') {
            return $this->buildPackagesTree();
        }

        [$segments, $operation] = $this->parseDiscoveryId($id);

        if ($operation !== null) {
            return $this->getOperation($segments[0], $segments[1], $segments[2], $operation);
        }

        return match (count($segments)) {
            1 => $this->buildPackageTree($segments[0]),
            2 => $this->buildComponentTree($segments[0], $segments[1]),
            3 => $this->buildWorkerTree($segments[0], $segments[1], $segments[2]),
            default => throw InvalidDiscoveryIdException::forId($id),
        };
    }

    /**
     * Parses a discovery id into its dot-separated segments and, when `::`
     * is present, the operation name after it — the shared validation
     * `describe()` and `tree()` both build on.
     *
     * @param string $id
     * @return array{0: string[], 1: string|null}
     */
    private function parseDiscoveryId(string $id): array
    {
        $parts = explode('::', $id, 2);

        if (count($parts) === 2) {
            [$path, $operation] = $parts;

            if ($operation === '') {
                throw InvalidDiscoveryIdException::forId($id);
            }

            $segments = explode('.', $path);

            if (count($segments) !== 3 || in_array('', $segments, true)) {
                throw InvalidDiscoveryIdException::forId($id);
            }

            return [$segments, $operation];
        }

        $segments = explode('.', $id);

        if (count($segments) > 3 || in_array('', $segments, true)) {
            throw InvalidDiscoveryIdException::forId($id);
        }

        return [$segments, null];
    }

    /**
     * Builds the fully nested tree of every package, component, worker and
     * operation — the same pruning rules as `getPackages()` apply, just
     * computed once per branch and reused for both the emptiness check and
     * the nested value, instead of walking each branch twice.
     *
     * @return array
     */
    private function buildPackagesTree(): array
    {
        $packages = array_keys($this->packageRegistry->getPackages());

        $trees = [];
        foreach ($packages as $package) {
            $tree = $this->buildPackageTree($package);

            if ($this->operationPolicy !== null && $tree['components'] === []) {
                continue;
            }

            $trees[] = $tree;
        }

        return $trees;
    }

    /**
     * Builds one package's data with its components (and, in turn, their
     * workers and operations) nested underneath.
     *
     * @param string $package
     * @return array
     */
    private function buildPackageTree(string $package): array
    {
        $components = array_keys(
            $this->packageRegistry
            ->getPackage($package)
            ->getComponents()
        );

        $trees = [];
        foreach ($components as $component) {
            $tree = $this->buildComponentTree($package, $component);

            if ($this->operationPolicy !== null && $tree['workers'] === []) {
                continue;
            }

            $trees[] = $tree;
        }

        $data = $this->getPackage($package);
        $data['components'] = $trees;

        return $data;
    }

    /**
     * Builds one component's data with its workers (and, in turn, their
     * operations) nested underneath.
     *
     * @param string $package
     * @param string $component
     * @return array
     */
    private function buildComponentTree(string $package, string $component): array
    {
        $workers = array_keys(
            $this->packageRegistry
            ->getPackage($package)
            ->getComponent($component)
            ->getWorkers()
        );

        $trees = [];
        foreach ($workers as $worker) {
            $tree = $this->buildWorkerTree($package, $component, $worker);

            if ($this->operationPolicy !== null && $tree['operations'] === []) {
                continue;
            }

            $trees[] = $tree;
        }

        $data = $this->getComponent($package, $component);
        $data['workers'] = $trees;

        return $data;
    }

    /**
     * Builds one worker's data with its operations nested underneath —
     * operations are always the leaves, nothing nests under them.
     *
     * @param string $package
     * @param string $component
     * @param string $worker
     * @return array
     */
    private function buildWorkerTree(string $package, string $component, string $worker): array
    {
        $data = $this->getWorker($package, $component, $worker);
        $data['operations'] = $this->getOperations($package, $component, $worker);

        return $data;
    }

    /**
     * Merges an operation's discovery id with its already-reflected info.
     *
     * @param string $package
     * @param string $component
     * @param string $worker
     * @param string $operation
     * @param array $info
     * @return array
     */
    private function buildOperation(
        string $package,
        string $component,
        string $worker,
        string $operation,
        array $info
    ): array {
        return array_merge([
            'id' => sprintf(
                '%s.%s.%s::%s',
                $package,
                $component,
                $worker,
                $operation
            ),
        ], $info);
    }
}
