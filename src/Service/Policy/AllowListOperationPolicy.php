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

use Derafu\BackboneDispatcher\Contract\OperationPolicyInterface;

/**
 * Allows only operations matching one of a configured list of patterns.
 *
 * Each pattern uses the same `"package.component.worker::operation"` shape
 * as the discovery/invocation id (see `Explorer`/`OperationRequest`), and
 * may use `fnmatch()` wildcards to allow more than one operation without
 * enumerating each of them, e.g. `"billing.invoice.builder::*"` for every
 * operation of that worker, or `"billing.*"` for an entire package.
 */
final class AllowListOperationPolicy implements OperationPolicyInterface
{
    /**
     * @param string[] $patterns
     */
    public function __construct(
        private readonly array $patterns,
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
        $id = sprintf('%s.%s.%s::%s', $package, $component, $worker, $operation);

        foreach ($this->patterns as $pattern) {
            if (fnmatch($pattern, $id)) {
                return true;
            }
        }

        return false;
    }
}
