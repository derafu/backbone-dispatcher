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

use Throwable;

/**
 * What a step produced.
 */
final readonly class StepResult
{
    /**
     * @param string $name The name of the step.
     * @param bool $success Whether the step worked.
     * @param mixed $value The serialized value of a successful step.
     * @param object|null $object The object that a deserialize step built.
     * @param array{type: string, title: string, detail: string}|null $problem
     * The problem of a dispatch step that failed.
     * @param Throwable|null $throwable The exception of a deserialize step
     * that failed.
     */
    public function __construct(
        public string $name,
        public bool $success,
        public mixed $value = null,
        public ?object $object = null,
        public ?array $problem = null,
        public ?Throwable $throwable = null,
    ) {
    }

    /**
     * The step as `!ref` sees it: only `value` and `problem`.
     *
     * @return array{value: mixed, problem: array{type: string, title: string, detail: string}|null}
     */
    public function toReference(): array
    {
        return ['value' => $this->value, 'problem' => $this->problem];
    }
}
