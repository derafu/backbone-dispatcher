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
use RuntimeException;

/**
 * A documented arithmetic service.
 *
 * Provides documentation with links and declared operation errors.
 *
 * @link https://example.com/service Service documentation.
 * @link https://example.com/reference
 */
class DocumentedService
{
    /**
     * Doubles a nonnegative number.
     *
     * @param int $value The nonnegative input.
     * @return int The doubled value.
     * @throws RuntimeException When the input is negative.
     * @link https://example.com/double Operation documentation.
     */
    #[Operation]
    public function double(int $value = 0): int
    {
        if ($value < 0) {
            throw new RuntimeException('The input must be nonnegative.');
        }

        return $value * 2;
    }
}
