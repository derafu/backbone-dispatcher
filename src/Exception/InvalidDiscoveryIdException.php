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
 * Exception for a discovery identifier passed to `ExplorerInterface::describe()`
 * that does not match `"package"`, `"package.component"`,
 * `"package.component.worker"` or `"package.component.worker::operation"`.
 *
 * The shape is identical to `InvalidOperationIdException`'s once an
 * operation is present — both use `::` before it, on purpose (see
 * `Explorer`'s own docblock). What's different, and why this stays a
 * separate exception: `describe()` also accepts a *partial* id (1 to 3
 * dot-separated segments, no operation at all) to browse a package,
 * component or worker — something `OperationRequest::fromId()` (always a
 * complete, 4-part invocation) never allows.
 */
class InvalidDiscoveryIdException extends TranslatableInvalidArgumentException
{
    /**
     * Returns a new exception for a malformed discovery identifier.
     *
     * @param string $id The identifier that could not be parsed.
     * @return self
     */
    public static function forId(string $id): self
    {
        return new self([
            'The discovery id {id} is not valid. It must have the structure: package[.component[.worker[::operation]]].',
            'id' => $id,
        ]);
    }
}
