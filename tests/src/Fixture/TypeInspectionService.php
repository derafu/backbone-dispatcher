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

use Countable;
use RuntimeException;
use Stringable;

class TypeInspectionService
{
    public function intersection(Countable&Stringable $value): Countable&Stringable
    {
        return $value;
    }

    public function union((Countable&Stringable)|int $value): (Countable&Stringable)|int
    {
        return $value;
    }

    public function nullable(?string $value): ?string
    {
        return $value;
    }

    /**
     * @param string $value
     * @return string
     * @throws RuntimeException
     */
    public function withoutDescriptions(string $value): string
    {
        if ($value === '') {
            throw new RuntimeException('The value must not be empty.');
        }

        return $value;
    }
}
