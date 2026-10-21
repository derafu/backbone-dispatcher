<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsBackboneDispatcher\Fixture;

use Derafu\Backbone\Attribute\Operation;
use Symfony\Component\VarExporter\LazyObjectInterface;

/**
 * A lazy service without a parent class.
 */
class StandaloneLazyService implements LazyObjectInterface
{
    private bool $initialized = false;

    public function isLazyObjectInitialized(bool $partial = false): bool
    {
        return $this->initialized;
    }

    public function initializeLazyObject(): object
    {
        $this->initialized = true;

        return $this;
    }

    public function resetLazyObject(): bool
    {
        $this->initialized = false;

        return true;
    }

    #[Operation]
    public function describe(string $name): string
    {
        return $name;
    }
}
