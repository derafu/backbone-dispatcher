<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsBackboneDispatcher\Fixture\Harness;

use Derafu\Backbone\Contract\PackageInterface;
use Derafu\Backbone\Contract\PackageRegistryInterface;
use RuntimeException;

/**
 * A registry that cannot list its packages.
 */
class ThrowingPackageRegistry implements PackageRegistryInterface
{
    public function registerPackage(string $name, PackageInterface $package): static
    {
        return $this;
    }

    public function getPackage(string $name): PackageInterface
    {
        throw new RuntimeException('The registry is broken.');
    }

    public function getPackages(): array
    {
        throw new RuntimeException('The registry is broken.');
    }

    public function hasPackage(string $name): bool
    {
        return false;
    }
}
