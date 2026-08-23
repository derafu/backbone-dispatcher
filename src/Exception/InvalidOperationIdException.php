<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\BackboneDispatcher\Exception;

use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;

/**
 * Exception for an operation identifier that does not match the expected
 * `"package.component.worker::operation"` format.
 *
 * Thrown while parsing the id itself (`OperationRequest::fromId()`), before
 * a package, component or worker has even been identified — unrelated to
 * `ResolverException`, which is about resolving the parameters of an
 * operation that has already been identified.
 */
class InvalidOperationIdException extends TranslatableInvalidArgumentException
{
    /**
     * Returns a new exception for a malformed operation identifier.
     *
     * @param string $id The identifier that could not be parsed.
     * @return self
     */
    public static function forId(string $id): self
    {
        return new self([
            'The operation id {id} is not valid. It must have the structure: package.component.worker::operation.',
            'id' => $id,
        ]);
    }
}
