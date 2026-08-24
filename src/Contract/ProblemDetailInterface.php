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
use Stringable;

/**
 * Represents an error using RFC 7807 (Problem Details) as a base, without
 * anything specific to HTTP.
 *
 * `derafu/http` has its own `ProblemDetailInterface` with the same base,
 * plus `getStatus()`/`getHttpStatus()`/`getRequest()`/`getHeaders()` for
 * the HTTP transport. This is the transport-agnostic subset of that same
 * idea, used here so `OperationResult`'s failure case has a real shape
 * instead of an ad-hoc array.
 *
 * @see https://www.rfc-editor.org/rfc/rfc7807.html
 */
interface ProblemDetailInterface extends Stringable, JsonSerializable
{
    /**
     * A URI reference [RFC3986] that identifies the problem type.
     *
     * @return string
     */
    public function getType(): string;

    /**
     * A short, human-readable summary of the problem type.
     *
     * @return string
     */
    public function getTitle(): string;

    /**
     * A human-readable explanation specific to this occurrence of the
     * problem.
     *
     * @return string
     */
    public function getDetail(): string;

    /**
     * An identifier for the specific occurrence of the problem.
     *
     * In `derafu/http` this is a URI (the request path). Here it identifies
     * whatever was being done when the problem occurred — an operation
     * being dispatched (e.g. `"package.component.worker::operation"`) or a
     * discovery id being explored (e.g. `"package.component"`).
     *
     * @return string|null
     */
    public function getInstance(): ?string;

    /**
     * Gets a safe representation of the throwable that caused this problem.
     *
     * @return SafeThrowableInterface
     */
    public function getThrowable(): SafeThrowableInterface;

    /**
     * Gets additional, problem-specific context.
     *
     * @return array
     */
    public function getContext(): array;

    /**
     * Gets when the error occurred.
     *
     * @return string
     */
    public function getTimestamp(): string;

    /**
     * Gets the application environment.
     *
     * @return string
     */
    public function getEnvironment(): string;

    /**
     * Checks if debug mode is enabled.
     *
     * @return bool
     */
    public function isDebug(): bool;

    /**
     * Converts the problem detail to an array.
     *
     * @return array
     */
    public function toArray(): array;
}
