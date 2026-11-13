<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsBackboneDispatcher\Fixture\Harness;

/**
 * The violations of `BadConformance`, all of them excluded with their reason,
 * and one exclusion that has no violation.
 */
class ExcludingConformance extends BadConformance
{
    public bool $withStaleExclusion = false;

    protected function exclusions(): array
    {
        $bad = 'example_package.example_component.bad_worker::';
        $exclusions = [
            'names_follow_the_convention' => [$bad . 'plainOperation' => 'Named in camel case on purpose.'],
            'operations_are_documented' => [
                $bad . 'plainOperation' => 'Not documented on purpose.',
                $bad . 'object_example' => 'Not documented on purpose.',
                $bad . 'intersection' => 'Not documented on purpose.',
            ],
            'parameters_have_examples' => [$bad . 'plainOperation' => 'No examples on purpose.'],
            'examples_are_encodable_as_json' => [$bad . 'object_example' => 'The example is an object on purpose.'],
            'object_parameters_have_a_deserializer' => [$bad . 'plainOperation' => 'No deserializer on purpose.'],
            'return_types_are_serializable' => [
                $bad . 'plainOperation' => 'Returns an object on purpose.',
                $bad . 'object_example' => 'Returns mixed on purpose.',
                $bad . 'intersection' => 'Returns an intersection on purpose.',
            ],
        ];

        if ($this->withStaleExclusion) {
            $exclusions['parameters_have_examples'][$bad . 'object_example'] = 'It has an example, so this is stale.';
        }

        return $exclusions;
    }
}
