<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\BackboneDispatcher\Service\Policy;

use Derafu\Backbone\Contract\PackageRegistryInterface;
use Derafu\BackboneDispatcher\Contract\InspectorInterface;
use Derafu\BackboneDispatcher\Contract\OperationPolicyInterface;

/**
 * Allows only operations explicitly tagged with the `#[Operation]`
 * attribute from `derafu/backbone`.
 *
 * Recommended for anything meant to be reached from outside PHP: it is the
 * only policy that draws a real line between "a public method that happens
 * to exist" and "an operation meant to be exposed" — see
 * `AllowAllOperationPolicy`'s docblock for why that distinction matters
 * (worker infrastructure methods from `JobsAwareTrait`/`HandlersAwareTrait`/
 * `OptionsAwareTrait`/`ServiceInterface` are otherwise just as dispatchable
 * as real business operations).
 */
final class TaggedOperationPolicy implements OperationPolicyInterface
{
    public function __construct(
        private readonly PackageRegistryInterface $packageRegistry,
        private readonly InspectorInterface $inspector,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function isAllowed(
        string $package,
        string $component,
        string $worker,
        string $operation
    ): bool {
        $workerInstance =
            $this->packageRegistry
            ->getPackage($package)
            ->getComponent($component)
            ->getWorker($worker)
        ;

        return $this->inspector->hasOperationAttribute($workerInstance, $operation);
    }
}
