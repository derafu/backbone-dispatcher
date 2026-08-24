<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\BackboneDispatcher\ValueObject;

use Derafu\BackboneDispatcher\Contract\ExecutionMetadataInterface;

/**
 * Statistics about one execution, computed from a snapshot taken at its
 * start (`since()`) and one taken right now, at its end.
 */
class ExecutionMetadata implements ExecutionMetadataInterface
{
    private function __construct(
        private readonly string $startedAt,
        private readonly string $finishedAt,
        private readonly float $realTime,
        private readonly float $userTime,
        private readonly float $systemTime,
        private readonly int $memoryUsed,
        private readonly int $peakMemory,
        private readonly int $pid,
        private readonly float $loadAverage1Min,
        private readonly float $loadAverage5Min,
        private readonly float $loadAverage15Min
    ) {
    }

    /**
     * Builds the metadata of an execution that started at `$startedAt`.
     *
     * `$monotonicStart`, `$startMemory` and `$startCpuUsage` must be
     * captured by the caller right before the execution being measured
     * starts (`hrtime(true)`, `memory_get_usage(true)` and `getrusage()`,
     * respectively) — this method only knows how to close the measurement,
     * not when to open it.
     *
     * @param string $startedAt When the execution started, `DATE_ATOM`.
     * @param int $monotonicStart `hrtime(true)` reading taken at the start.
     * A monotonic clock value with no relation to wall-clock time, used
     * only to compute `getRealTime()` safely against clock adjustments.
     * @param int $startMemory `memory_get_usage(true)` reading taken at
     * the start.
     * @param array $startCpuUsage `getrusage()` reading taken at the start.
     * @return self
     */
    public static function since(
        string $startedAt,
        int $monotonicStart,
        int $startMemory,
        array $startCpuUsage
    ): self {
        $endCpuUsage = getrusage();
        $loadAverage = sys_getloadavg();

        return new self(
            startedAt: $startedAt,
            finishedAt: date(DATE_ATOM),
            realTime: (hrtime(true) - $monotonicStart) / 1_000_000_000,
            userTime: self::cpuSeconds($endCpuUsage, 'ru_utime')
                - self::cpuSeconds($startCpuUsage, 'ru_utime'),
            systemTime: self::cpuSeconds($endCpuUsage, 'ru_stime')
                - self::cpuSeconds($startCpuUsage, 'ru_stime'),
            memoryUsed: memory_get_usage(true) - $startMemory,
            peakMemory: memory_get_peak_usage(true),
            pid: getmypid(),
            loadAverage1Min: $loadAverage[0],
            loadAverage5Min: $loadAverage[1],
            loadAverage15Min: $loadAverage[2],
        );
    }

    /**
     * Reads one `getrusage()` field pair (`"{$prefix}.tv_sec"` +
     * `"{$prefix}.tv_usec"`) as a single seconds value.
     *
     * @param array $usage
     * @param string $prefix `'ru_utime'` or `'ru_stime'`.
     * @return float
     */
    private static function cpuSeconds(array $usage, string $prefix): float
    {
        return $usage["{$prefix}.tv_sec"] + $usage["{$prefix}.tv_usec"] / 1_000_000;
    }

    /**
     * {@inheritDoc}
     */
    public function getStartedAt(): string
    {
        return $this->startedAt;
    }

    /**
     * {@inheritDoc}
     */
    public function getFinishedAt(): string
    {
        return $this->finishedAt;
    }

    /**
     * {@inheritDoc}
     */
    public function getRealTime(): float
    {
        return $this->realTime;
    }

    /**
     * {@inheritDoc}
     */
    public function getUserTime(): float
    {
        return $this->userTime;
    }

    /**
     * {@inheritDoc}
     */
    public function getSystemTime(): float
    {
        return $this->systemTime;
    }

    /**
     * {@inheritDoc}
     */
    public function getMemoryUsed(): int
    {
        return $this->memoryUsed;
    }

    /**
     * {@inheritDoc}
     */
    public function getPeakMemory(): int
    {
        return $this->peakMemory;
    }

    /**
     * {@inheritDoc}
     */
    public function getPid(): int
    {
        return $this->pid;
    }

    /**
     * {@inheritDoc}
     */
    public function getLoadAverage1Min(): float
    {
        return $this->loadAverage1Min;
    }

    /**
     * {@inheritDoc}
     */
    public function getLoadAverage5Min(): float
    {
        return $this->loadAverage5Min;
    }

    /**
     * {@inheritDoc}
     */
    public function getLoadAverage15Min(): float
    {
        return $this->loadAverage15Min;
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'startedAt' => $this->startedAt,
            'finishedAt' => $this->finishedAt,
            'realTime' => $this->realTime,
            'userTime' => $this->userTime,
            'systemTime' => $this->systemTime,
            'memoryUsed' => $this->memoryUsed,
            'peakMemory' => $this->peakMemory,
            'pid' => $this->pid,
            'loadAverage1Min' => $this->loadAverage1Min,
            'loadAverage5Min' => $this->loadAverage5Min,
            'loadAverage15Min' => $this->loadAverage15Min,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
