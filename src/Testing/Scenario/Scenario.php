<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\BackboneDispatcher\Testing\Scenario;

/**
 * A scenario: the steps of a YAML file, in order.
 */
final readonly class Scenario
{
    /**
     * @param string $path The path of the file.
     * @param string $description
     * @param list<string> $requires The requirements of the whole scenario.
     * @param array<string, Step> $steps The steps, by name, in order.
     */
    public function __construct(
        public string $path,
        public string $description,
        public array $requires,
        public array $steps,
    ) {
    }
}
