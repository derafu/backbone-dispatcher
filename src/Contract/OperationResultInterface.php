<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\BackboneDispatcher\Contract;

use JsonSerializable;

/**
 * The outcome of dispatching a single `OperationRequestInterface`: either
 * the value the operation returned, or a `ProblemDetailInterface`
 * describing why it could not be completed. Never both, and never neither.
 *
 * Construction (`success()`/`failure()`) is intentionally NOT part of this
 * interface: it's a named-constructor concern of the concrete
 * `OperationResult`, not part of the contract callers consume.
 */
interface OperationResultInterface extends JsonSerializable
{
    public function isSuccess(): bool;

    /**
     * @return mixed The value returned by the operation. `null` if this
     * result is a failure (use `isSuccess()` to tell the two apart, not
     * this method returning `null`, since `null` is also a valid
     * successful value).
     */
    public function getValue(): mixed;

    public function getProblem(): ?ProblemDetailInterface;

    /**
     * Converts the operation result to an array.
     *
     * @return array
     */
    public function toArray(): array;
}
