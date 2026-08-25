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

use Derafu\Backbone\Contract\ComponentInterface;
use Derafu\Backbone\Contract\WorkerInterface;
use Derafu\Backbone\Exception\WorkerNotFoundException;
use Derafu\Config\Trait\OptionsAwareTrait;

/**
 * A component without a description.
 *
 * `getDescription()` returns `null` here, as if its `#[Component]`-style
 * attribute never set one — used to verify `Explorer` falls back to this
 * very PHPDoc description instead of reporting `null`.
 */
class ExampleComponentWithoutDescription implements ComponentInterface
{
    use OptionsAwareTrait;

    /**
     * @param array<string, WorkerInterface> $workers
     */
    public function __construct(
        private readonly array $workers,
    ) {
    }

    public function getId(): int|string
    {
        return 'example_component_without_description';
    }

    public function getName(): string
    {
        return 'Example Component Without Description';
    }

    public function getDescription(): ?string
    {
        return null;
    }

    public function __toString(): string
    {
        return $this->getName();
    }

    public function getWorker(string $name): WorkerInterface
    {
        if (!isset($this->workers[$name])) {
            throw WorkerNotFoundException::forWorker($name);
        }

        return $this->workers[$name];
    }

    public function getWorkers(): array
    {
        return $this->workers;
    }
}
