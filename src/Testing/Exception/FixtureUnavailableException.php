<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\BackboneDispatcher\Testing\Exception;

use Derafu\Translation\Exception\Core\TranslatableLogicException;

/**
 * Exception for a fixture whose requirement is not available in the current
 * environment (for example a service that needs Docker).
 *
 * It is not a failure: `ScenarioTestCase` turns it into a skipped test that
 * says why.
 */
class FixtureUnavailableException extends TranslatableLogicException
{
    /**
     * A requirement that its check reported as not available.
     *
     * @param string $requirement The requirement name.
     * @param string $reason The reason that the check of the requirement gave.
     * @return self
     */
    public static function forRequirement(string $requirement, string $reason): self
    {
        return new self([
            'The requirement {requirement} is not available: {reason}',
            'requirement' => $requirement,
            'reason' => $reason,
        ]);
    }
}
