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
 * What a step of a scenario does.
 */
enum StepKind: string
{
    /**
     * Dispatches an operation with `SafeDispatcherInterface`.
     */
    case Dispatch = 'dispatch';

    /**
     * Builds an object with `ObjectFactoryInterface`.
     */
    case Deserialize = 'deserialize';
}
