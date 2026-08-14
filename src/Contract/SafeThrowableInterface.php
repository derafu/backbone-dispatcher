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
 * A safe, serializable snapshot of a `Throwable`.
 *
 * Ported from `derafu/http`'s `SafeThrowableInterface`, which has nothing
 * HTTP-specific in it either.
 */
interface SafeThrowableInterface extends Stringable, JsonSerializable
{
    /**
     * Gets the throwable class name.
     *
     * @return string
     */
    public function getClass(): string;

    /**
     * Gets the original code for this error.
     *
     * @return int
     */
    public function getCode(): int;

    /**
     * Gets the error message.
     *
     * @return string
     */
    public function getMessage(): string;

    /**
     * Gets the file where the error occurred.
     *
     * @return string
     */
    public function getFile(): string;

    /**
     * Gets the line where the error occurred.
     *
     * @return integer
     */
    public function getLine(): int;

    /**
     * Gets the stack trace.
     *
     * @return array<int,mixed>
     */
    public function getTrace(): array;

    /**
     * Gets the stack trace as a string.
     *
     * @return string
     */
    public function getTraceAsString(): string;

    /**
     * Gets the previous error if any.
     *
     * @return SafeThrowableInterface|null
     */
    public function getPrevious(): ?SafeThrowableInterface;

    /**
     * Converts the safe throwable to an array.
     *
     * @return array
     */
    public function toArray(): array;
}
