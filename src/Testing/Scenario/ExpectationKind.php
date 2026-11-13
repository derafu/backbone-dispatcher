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
 * How the outcome of a step is checked.
 */
enum ExpectationKind: string
{
    /**
     * Nothing is compared: the step only has to succeed.
     */
    case None = 'none';

    /**
     * The step has to succeed and match `expect`.
     */
    case Expect = 'expect';

    /**
     * The step has to fail and match `failure`.
     */
    case Failure = 'failure';

    /**
     * The step has to succeed and match `expect`, or fail and match `failure`.
     */
    case Either = 'either';
}
