<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\BackboneDispatcher\Service;

use Derafu\Backbone\Contract\PackageRegistryInterface;

/**
 * Walks the package registry of the application and returns plain data
 * describing its packages, components, workers and jobs.
 *
 * Transport-agnostic: it does not add links, URLs or any other concept tied
 * to a specific transport (e.g. HATEOAS `_links` for HTTP). Transports that
 * need that can decorate the arrays returned here.
 */
class Explorer
{
    public function __construct(
        private PackageRegistryInterface $packageRegistry,
        private Inspector $inspector,
    ) {
    }

    /**
     * Returns the list of packages of the application.
     *
     * @return array
     */
    public function getPackages(): array
    {
        return array_map(
            fn ($package) => $this->getPackage($package),
            array_keys($this->packageRegistry->getPackages())
        );
    }

    /**
     * Returns the list of components of a package.
     *
     * @param string $package
     * @return array
     */
    public function getComponents(string $package): array
    {
        return array_map(
            fn ($component) => $this->getComponent($package, $component),
            array_keys(
                $this->packageRegistry
                ->getPackage($package)
                ->getComponents()
            )
        );
    }

    /**
     * Returns the list of workers of a component.
     *
     * @param string $package
     * @param string $component
     * @return array
     */
    public function getWorkers(string $package, string $component): array
    {
        return array_map(
            fn ($worker) => $this->getWorker($package, $component, $worker),
            array_keys(
                $this->packageRegistry
                ->getPackage($package)
                ->getComponent($component)
                ->getWorkers()
            )
        );
    }

    /**
     * Returns the list of jobs of a worker.
     *
     * This will only return as jobs the public methods that the worker has
     * defined. It will not search for specific jobs for the worker, or search
     * for classes that implement JobInterface.
     *
     * @param string $package
     * @param string $component
     * @param string $worker
     * @return array
     */
    public function getJobs(
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
        $methods = array_map(
            fn ($key, $value) => array_merge($value, ['name' => $key]),
            array_keys($methods),
            $methods
        );

        return array_map(
            fn ($info) => $this->getJob($package, $component, $worker, $info),
            $methods
        );
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
        $data = [
            'id' => $package,
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
        $data = [
            'id' => sprintf(
                '%s.%s',
                $package,
                $component
            ),
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
     * @param boolean $withJobs
     * @return array
     */
    public function getWorker(
        string $package,
        string $component,
        string $worker,
        bool $withJobs = false
    ): array {
        $data = [
            'id' => sprintf(
                '%s.%s.%s',
                $package,
                $component,
                $worker
            ),
        ];

        if ($withJobs) {
            $data['jobs'] = $this->getJobs($package, $component, $worker);
        }

        return $data;
    }

    /**
     * Returns the data of a specific job.
     *
     * @param string $package
     * @param string $component
     * @param string $worker
     * @param array $info
     * @return array
     */
    public function getJob(
        string $package,
        string $component,
        string $worker,
        array $info
    ): array {
        $job = $info['name'];
        unset($info['name']);

        return array_merge([
            'id' => sprintf(
                '%s.%s.%s.%s',
                $package,
                $component,
                $worker,
                $job
            ),
        ], $info);
    }
}
