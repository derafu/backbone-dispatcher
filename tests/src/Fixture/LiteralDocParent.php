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
 * Keeps $1, ${name}, and \path literal.
 *
 * Preserves $0 and \directory in the description.
 */
class LiteralDocParent
{
    /**
     * Keeps $1, ${name}, and \path literal.
     *
     * Preserves $0 and \directory in the description.
     */
    public function describe(): string
    {
        return 'parent';
    }
}
