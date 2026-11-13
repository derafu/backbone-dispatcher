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
 * What a step has to produce.
 *
 * The expected values are the raw data of the file: a `TaggedValue` stays as
 * it is until it is compared (`!ref` is resolved against the results of the
 * previous steps, `!any` and `!instanceof` are assertions).
 */
final readonly class Expectation
{
    /**
     * @param ExpectationKind $kind
     * @param array<string, mixed> $expect The JMESPath expression of the
     * serialized value => the expected value. Empty unless the kind is
     * `Expect` or `Either`.
     * @param array<string, mixed> $call The `method()` or `method().expression`
     * => the expected value. Only for a deserialize step.
     * @param Failure|null $failure Set when the kind is `Failure` or `Either`.
     */
    public function __construct(
        public ExpectationKind $kind = ExpectationKind::None,
        public array $expect = [],
        public array $call = [],
        public ?Failure $failure = null,
    ) {
    }
}
