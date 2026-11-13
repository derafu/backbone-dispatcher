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
 * A step of a scenario.
 */
final readonly class Step
{
    /**
     * @param string $name The name of the step, used by `!ref`.
     * @param StepKind $kind
     * @param string $target The operation id of a dispatch step, or the type
     * of a deserialize step.
     * @param mixed $input The parameters of a dispatch step (a map), or the
     * data of a deserialize step (a string, a map or a `TaggedValue`). Raw data
     * of the file: it can have `TaggedValue` objects.
     * @param Expectation $expectation
     */
    public function __construct(
        public string $name,
        public StepKind $kind,
        public string $target,
        public mixed $input,
        public Expectation $expectation,
    ) {
    }
}
