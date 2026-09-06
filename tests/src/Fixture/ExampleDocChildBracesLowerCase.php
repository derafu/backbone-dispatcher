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
 * {@inheritdoc}
 */
class ExampleDocChildBracesLowerCase extends ExampleDocParent
{
    /**
     * {@inheritdoc}
     */
    public function describe(): string
    {
        return 'child';
    }
}
