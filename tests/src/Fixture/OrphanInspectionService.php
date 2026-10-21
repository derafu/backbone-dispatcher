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

use LogicException;
use RuntimeException;

/** {@inheritDoc} */
class OrphanInspectionService
{
    /** {@inheritDoc} */
    public function inspect(): void
    {
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public function untyped($value)
    {
        return $value;
    }

    /**
     * @param array{name: string} $item
     * @return array{name: string}
     */
    public function shapedArray(array $item): array
    {
        return $item;
    }

    /**
     * Reports valid exceptions even when another tag has a malformed link.
     *
     * @throws RuntimeException {@link }
     * @throws LogicException When the operation cannot continue.
     */
    public function malformedLink(): void
    {
        throw new LogicException('The operation cannot continue.');
    }
}
