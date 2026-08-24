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
 * The outcome of one `SafeExplorerInterface` call: either the value
 * `ExplorerInterface` returned, or a `ProblemDetailInterface` describing why
 * it could not be completed. Never both, and never neither.
 *
 * Deliberately not the same type as `OperationResultInterface`: both look
 * alike today (success/value or failure/problem), but one is about
 * dispatching an operation and the other about exploring the package tree —
 * concepts that may diverge (e.g. `OperationResultInterface` gaining
 * execution-specific data later) without either one dragging the other
 * along.
 *
 * Construction (`success()`/`failure()`) is intentionally NOT part of this
 * interface: it's a named-constructor concern of the concrete
 * `DiscoveryResult`, not part of the contract callers consume.
 */
interface DiscoveryResultInterface extends JsonSerializable
{
    public function isSuccess(): bool;

    /**
     * @return mixed The value `ExplorerInterface` returned. `null` if this
     * result is a failure (use `isSuccess()` to tell the two apart, not
     * this method returning `null`, since `null` is also a valid
     * successful value, e.g. a worker's `description`).
     */
    public function getValue(): mixed;

    public function getProblem(): ?ProblemDetailInterface;

    /**
     * Converts the discovery result to an array.
     *
     * @return array
     */
    public function toArray(): array;
}
