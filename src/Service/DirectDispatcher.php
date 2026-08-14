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
use Derafu\BackboneDispatcher\Contract\DirectDispatcherInterface;
use Invoker\InvokerInterface;

/**
 * Resolves a worker from the package registry and invokes an operation (a
 * public method) on it with the given parameters.
 *
 * Returns exactly what the operation returned, with no serialization
 * applied: this is the most direct of the three dispatchers, meant for
 * callers that are not about to hand the result across a language or
 * process boundary (e.g. a PHP-only caller that wants to keep working with
 * the real domain object). Serialization is `SafeDispatcherInterface`'s
 * responsibility, since it is the tier that actually exists for crossing
 * such a boundary.
 */
class DirectDispatcher implements DirectDispatcherInterface
{
    /**
     * Constructor with dependencies.
     *
     * @param PackageRegistryInterface $packageRegistry
     * @param Resolver $resolver
     * @param InvokerInterface $invoker
     */
    public function __construct(
        private PackageRegistryInterface $packageRegistry,
        private Resolver $resolver,
        private InvokerInterface $invoker,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function dispatch(
        string $package,
        string $component,
        string $worker,
        string $operation,
        array $params = []
    ): mixed {
        $workerInstance =
            $this->packageRegistry
            ->getPackage($package)
            ->getComponent($component)
            ->getWorker($worker)
        ;

        $args = $this->resolver->resolve($workerInstance, $operation, $params);

        return $this->invoker->call([$workerInstance, $operation], $args);
    }
}
