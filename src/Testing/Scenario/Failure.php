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
 * What a step that must fail has to fail with.
 */
final readonly class Failure
{
    /**
     * @param list<string> $exceptions The class names of which one has to be
     * the exception (a deserialize step) or the title of the problem (a
     * dispatch step). Empty when it is not checked.
     * @param string|null $causedBy The class name that has to be the exception
     * or one in its chain of previous exceptions (a deserialize step only).
     * @param string|null $message A text that the message of the exception, or
     * the detail of the problem, has to contain.
     */
    public function __construct(
        public array $exceptions = [],
        public ?string $causedBy = null,
        public ?string $message = null,
    ) {
    }
}
