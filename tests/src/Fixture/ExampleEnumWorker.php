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
use Derafu\Backbone\Contract\WorkerInterface;
use Derafu\Backbone\Trait\HandlersAwareTrait;
use Derafu\Backbone\Trait\JobsAwareTrait;
use Derafu\Config\Trait\OptionsAwareTrait;

/**
 * A real worker with operations whose parameters are enums, used to exercise
 * the Resolver and the Caster against real, reflectable methods.
 */
class ExampleEnumWorker implements WorkerInterface
{
    use JobsAwareTrait;
    use HandlersAwareTrait;
    use OptionsAwareTrait;

    public function getId(): int|string
    {
        return 'example_enum_worker';
    }

    public function getName(): string
    {
        return 'Example Enum Worker';
    }

    public function getDescription(): ?string
    {
        return 'A worker with operations that take enums.';
    }

    public function __toString(): string
    {
        return $this->getName();
    }

    #[Operation]
    public function describe(
        ExampleBackedEnum $status,
        ExampleIntBackedEnum $level,
        ?ExampleBackedEnum $previous = null
    ): string {
        return $status->value . ':' . $level->value . ':' . ($previous->value ?? '-');
    }
}
