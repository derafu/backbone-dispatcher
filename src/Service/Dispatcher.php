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
use Derafu\BackboneDispatcher\Contract\DispatcherInterface;
use Derafu\BackboneDispatcher\Contract\SerializerInterface;
use Invoker\InvokerInterface;

/**
 * Resolves a worker from the package registry and invokes an operation (a
 * public method) on it with the given parameters.
 *
 * The return value is passed through the SerializerInterface before being
 * returned, so every transport (HTTP, phpy, CLI, ...) gets transport-safe
 * data without having to know the serialization convention of every domain
 * object.
 */
class Dispatcher implements DispatcherInterface
{
    /**
     * Constructor with dependencies.
     *
     * @param PackageRegistryInterface $packageRegistry
     * @param Resolver $resolver
     * @param InvokerInterface $invoker
     * @param SerializerInterface $serializer
     */
    public function __construct(
        private PackageRegistryInterface $packageRegistry,
        private Resolver $resolver,
        private InvokerInterface $invoker,
        private SerializerInterface $serializer,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function invoke(
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

        $result = $this->invoker->call([$workerInstance, $operation], $args);

        return $this->serializer->serialize($result);
    }
}
