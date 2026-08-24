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

use Derafu\BackboneDispatcher\Contract\DiscoveryResultInterface;
use Derafu\BackboneDispatcher\Contract\ExplorerInterface;
use Derafu\BackboneDispatcher\Contract\ProblemDetailInterface;
use Derafu\BackboneDispatcher\Contract\SafeExplorerInterface;
use Derafu\BackboneDispatcher\ValueObject\DiscoveryResult;
use Derafu\BackboneDispatcher\ValueObject\ProblemDetail;
use Derafu\BackboneDispatcher\ValueObject\SafeThrowable;
use Throwable;

/**
 * Wraps `ExplorerInterface` so that no `Throwable` ever crosses back to the
 * caller uncaught.
 *
 * Unlike `SafeDispatcher`, the successful value is not passed through
 * `SerializerInterface`: `ExplorerInterface` only ever returns plain
 * arrays/scalars built from ids, names, descriptions and reflected
 * parameter/result metadata — never a worker's raw domain object — so there
 * is nothing left to flatten.
 */
class SafeExplorer implements SafeExplorerInterface
{
    public function __construct(
        private readonly ExplorerInterface $explorer,
        private readonly string $environment = 'prod',
        private readonly bool $debug = false,
        private readonly ?string $projectDir = null,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function getPackages(): DiscoveryResultInterface
    {
        return $this->wrap(fn () => $this->explorer->getPackages(), null);
    }

    /**
     * {@inheritDoc}
     */
    public function getComponents(string $package): DiscoveryResultInterface
    {
        return $this->wrap(fn () => $this->explorer->getComponents($package), $package);
    }

    /**
     * {@inheritDoc}
     */
    public function getWorkers(string $package, string $component): DiscoveryResultInterface
    {
        return $this->wrap(
            fn () => $this->explorer->getWorkers($package, $component),
            sprintf('%s.%s', $package, $component)
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getOperations(
        string $package,
        string $component,
        string $worker
    ): DiscoveryResultInterface {
        return $this->wrap(
            fn () => $this->explorer->getOperations($package, $component, $worker),
            sprintf('%s.%s.%s', $package, $component, $worker)
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getPackage(
        string $package,
        bool $withComponents = false
    ): DiscoveryResultInterface {
        return $this->wrap(
            fn () => $this->explorer->getPackage($package, $withComponents),
            $package
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getComponent(
        string $package,
        string $component,
        bool $withWorkers = false
    ): DiscoveryResultInterface {
        return $this->wrap(
            fn () => $this->explorer->getComponent($package, $component, $withWorkers),
            sprintf('%s.%s', $package, $component)
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getWorker(
        string $package,
        string $component,
        string $worker,
        bool $withOperations = false
    ): DiscoveryResultInterface {
        return $this->wrap(
            fn () => $this->explorer->getWorker($package, $component, $worker, $withOperations),
            sprintf('%s.%s.%s', $package, $component, $worker)
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getOperation(
        string $package,
        string $component,
        string $worker,
        string $operation
    ): DiscoveryResultInterface {
        return $this->wrap(
            fn () => $this->explorer->getOperation($package, $component, $worker, $operation),
            sprintf('%s.%s.%s::%s', $package, $component, $worker, $operation)
        );
    }

    /**
     * {@inheritDoc}
     */
    public function describe(?string $id = null): DiscoveryResultInterface
    {
        return $this->wrap(fn () => $this->explorer->describe($id), $id);
    }

    /**
     * {@inheritDoc}
     */
    public function tree(?string $id = null): DiscoveryResultInterface
    {
        return $this->wrap(fn () => $this->explorer->tree($id), $id);
    }

    /**
     * Runs `$call`, turning whatever it returns into a successful
     * `DiscoveryResult`, or whatever it throws into a failure one.
     *
     * @param callable $call
     * @param string|null $instance
     * @return DiscoveryResultInterface
     */
    private function wrap(callable $call, ?string $instance): DiscoveryResultInterface
    {
        try {
            return DiscoveryResult::success($call());
        } catch (Throwable $e) {
            return DiscoveryResult::failure($this->buildProblem($e, $instance));
        }
    }

    private function buildProblem(Throwable $e, ?string $instance): ProblemDetailInterface
    {
        return new ProblemDetail(
            detail: $e->getMessage(),
            throwable: SafeThrowable::fromThrowable($e, $this->projectDir),
            timestamp: date(DATE_ATOM),
            environment: $this->environment,
            instance: $instance,
            debug: $this->debug,
        );
    }
}
