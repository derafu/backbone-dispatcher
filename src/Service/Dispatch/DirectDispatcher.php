<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\BackboneDispatcher\Service\Dispatch;

use Derafu\Backbone\Contract\PackageRegistryInterface;
use Derafu\BackboneDispatcher\Contract\DirectDispatcherInterface;
use Derafu\BackboneDispatcher\Contract\InspectorInterface;
use Derafu\BackboneDispatcher\Contract\OperationPolicyInterface;
use Derafu\BackboneDispatcher\Exception\OperationNotAllowedException;
use Derafu\BackboneDispatcher\Exception\OperationNotFoundException;
use Derafu\BackboneDispatcher\Service\Policy\AllowAllOperationPolicy;
use Derafu\BackboneDispatcher\Service\Resolution\Resolver;

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
 *
 * Two guards run before an operation is ever resolved or invoked, in this
 * order:
 *
 *   - `InspectorInterface::isOperation()`: does it exist at all, as a
 *     public method declared on the worker? This is a fact about the
 *     worker, independent of which `OperationPolicyInterface` is
 *     configured.
 *   - `OperationPolicyInterface::isAllowed()`: given that it exists, is it
 *     one this application chooses to expose? Defaults to
 *     `AllowAllOperationPolicy`, preserving the historical "any public
 *     method is an operation" behavior — see that class's docblock for why
 *     `TaggedOperationPolicy` is the recommended choice instead for
 *     anything reached from outside PHP.
 *
 * Because every other dispatcher tier (`TypedDispatcherInterface`,
 * `SafeDispatcherInterface`) wraps this one rather than duplicating it,
 * both guards apply no matter which tier a caller uses.
 *
 * Invokes the operation with a native named-argument call
 * (`$worker->$operation(...$args)`) rather than a generic invoker: `Resolver`
 * already produces `$args` as a plain, name-keyed, fully-cast array — an
 * intermediate invoker would only re-reflect the same callable `Resolver`
 * already resolved against.
 */
class DirectDispatcher implements DirectDispatcherInterface
{
    /**
     * Constructor with dependencies.
     *
     * @param PackageRegistryInterface $packageRegistry
     * @param InspectorInterface $inspector
     * @param Resolver $resolver
     * @param OperationPolicyInterface $operationPolicy
     */
    public function __construct(
        private PackageRegistryInterface $packageRegistry,
        private InspectorInterface $inspector,
        private Resolver $resolver,
        private OperationPolicyInterface $operationPolicy = new AllowAllOperationPolicy(),
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
        array $parameters = []
    ): mixed {
        $workerInstance =
            $this->packageRegistry
            ->getPackage($package)
            ->getComponent($component)
            ->getWorker($worker)
        ;

        if (!$this->inspector->isOperation($workerInstance, $operation)) {
            throw OperationNotFoundException::forOperation(
                $package,
                $component,
                $worker,
                $operation
            );
        }

        if (!$this->operationPolicy->isAllowed($package, $component, $worker, $operation)) {
            throw OperationNotAllowedException::forOperation(
                $package,
                $component,
                $worker,
                $operation
            );
        }

        $args = $this->resolver->resolve($workerInstance, $operation, $parameters);

        return $workerInstance->{$operation}(...$args);
    }
}
