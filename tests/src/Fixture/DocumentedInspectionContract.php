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

/**
 * A documented inspection contract.
 */
interface DocumentedInspectionContract
{
    /**
     * Processes the provided names.
     *
     * @param list<string> $items Names to process.
     * @return list<string> The processed names.
     */
    public function inspect(array $items): array;
}
