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
 * Statistics about one execution: how long it took, how much memory and CPU
 * it used, and how loaded the system was while it ran. A consumer decides
 * whether, and how, to use these — this package only collects them.
 *
 * Assumes Linux/macOS: built on `getrusage()` and `sys_getloadavg()`,
 * neither of which exists on Windows. No Windows support is offered.
 *
 * Construction (`since()`) is intentionally NOT part of this interface:
 * it's a named-constructor concern of the concrete `ExecutionMetadata`, not
 * part of the contract callers consume.
 */
interface ExecutionMetadataInterface extends JsonSerializable
{
    /**
     * @return string When this execution started, in `DATE_ATOM` format.
     */
    public function getStartedAt(): string;

    /**
     * @return string When this execution finished, in `DATE_ATOM` format.
     */
    public function getFinishedAt(): string;

    /**
     * The same moment as `getFinishedAt()`, as a Unix timestamp (seconds
     * since the epoch, with sub-second precision) instead of `DATE_ATOM` —
     * the flexible, directly-sortable/arithmetic-friendly representation a
     * transport-level response envelope (`meta.timestamp` in both the HTTP
     * API and the console bridge) uses, rather than `getFinishedAt()`'s
     * human-readable string. `ProblemDetailInterface::getTimestamp()`
     * reuses this exact value (not a separate `microtime(true)` call) so
     * both are always identical for the same dispatch.
     *
     * @return float
     */
    public function getTimestamp(): float;

    /**
     * @return float Wall-clock seconds elapsed, measured with a monotonic
     * clock (`hrtime()`) — the "real" of `time`. Includes time spent
     * waiting on I/O (network, disk, a database).
     */
    public function getRealTime(): float;

    /**
     * @return float CPU seconds spent in user-mode code — the "user" of
     * `time`. From `getrusage()`.
     */
    public function getUserTime(): float;

    /**
     * @return float CPU seconds spent in kernel-mode code (syscalls,
     * memory management) — the "sys" of `time`. From `getrusage()`.
     */
    public function getSystemTime(): float;

    /**
     * @return int Bytes allocated during this execution:
     * `memory_get_usage(true)` at the end minus at the start. Can be
     * negative if the garbage collector freed more than this execution
     * allocated.
     */
    public function getMemoryUsed(): int;

    /**
     * @return int Bytes. Peak memory allocated by the whole process up to
     * the end of this execution (`memory_get_peak_usage(true)`) — not
     * scoped to only this execution, but stable, unlike `getMemoryUsed()`,
     * against garbage-collection timing.
     */
    public function getPeakMemory(): int;

    /**
     * @return int The PID of the process that ran this.
     */
    public function getPid(): int;

    /**
     * @return float The system's 1-minute load average.
     */
    public function getLoadAverage1Min(): float;

    /**
     * @return float The system's 5-minute load average.
     */
    public function getLoadAverage5Min(): float;

    /**
     * @return float The system's 15-minute load average.
     */
    public function getLoadAverage15Min(): float;

    /**
     * Converts the execution metadata to an array.
     *
     * @return array
     */
    public function toArray(): array;
}
