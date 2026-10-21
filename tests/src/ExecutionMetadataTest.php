<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsBackboneDispatcher;

use Derafu\BackboneDispatcher\ValueObject\ExecutionMetadata;
use Derafu\TestsBackboneDispatcher\Fixture\SystemMetricFailures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExecutionMetadata::class)]
class ExecutionMetadataTest extends TestCase
{
    /**
     * @return array{string, int, int, array<string, int>}
     */
    private function startMeasuring(): array
    {
        $monotonicStart = hrtime(true);
        $this->assertIsInt($monotonicStart);
        $cpuUsage = getrusage();
        $this->assertIsArray($cpuUsage);

        return [date(DATE_ATOM), $monotonicStart, memory_get_usage(true), $cpuUsage];
    }

    public function testKeepsTheGivenStartedAtAndComputesAFinishedAt(): void
    {
        [$startedAt, $monotonicStart, $startMemory, $startCpuUsage] = $this->startMeasuring();

        $metadata = ExecutionMetadata::since($startedAt, $monotonicStart, $startMemory, $startCpuUsage);

        $this->assertSame($startedAt, $metadata->getStartedAt());
        $this->assertNotSame('', $metadata->getFinishedAt());
        $this->assertGreaterThanOrEqual(
            strtotime($startedAt),
            strtotime($metadata->getFinishedAt())
        );
    }

    public function testTimestampIsARealUnixEpochMatchingFinishedAt(): void
    {
        [$startedAt, $monotonicStart, $startMemory, $startCpuUsage] = $this->startMeasuring();

        $before = microtime(true);
        $metadata = ExecutionMetadata::since($startedAt, $monotonicStart, $startMemory, $startCpuUsage);
        $after = microtime(true);

        $this->assertGreaterThanOrEqual($before, $metadata->getTimestamp());
        $this->assertLessThanOrEqual($after, $metadata->getTimestamp());
        $this->assertSame(
            strtotime($metadata->getFinishedAt()),
            (int) $metadata->getTimestamp()
        );
    }

    public function testRealTimeAndCpuTimesAreNeverNegative(): void
    {
        [$startedAt, $monotonicStart, $startMemory, $startCpuUsage] = $this->startMeasuring();

        $metadata = ExecutionMetadata::since($startedAt, $monotonicStart, $startMemory, $startCpuUsage);

        $this->assertGreaterThanOrEqual(0.0, $metadata->getRealTime());
        $this->assertGreaterThanOrEqual(0.0, $metadata->getUserTime());
        $this->assertGreaterThanOrEqual(0.0, $metadata->getSystemTime());
    }

    public function testMemoryUsedReflectsRealAllocationKeptAliveUntilMeasured(): void
    {
        [$startedAt, $monotonicStart, $startMemory, $startCpuUsage] = $this->startMeasuring();

        $keepAlive = str_repeat('x', 5_000_000);

        $metadata = ExecutionMetadata::since($startedAt, $monotonicStart, $startMemory, $startCpuUsage);

        $this->assertGreaterThan(0, $metadata->getMemoryUsed());
        $this->assertGreaterThan(0, $metadata->getPeakMemory());
        $this->assertNotSame('', $keepAlive);
    }

    public function testPidMatchesTheCurrentProcess(): void
    {
        [$startedAt, $monotonicStart, $startMemory, $startCpuUsage] = $this->startMeasuring();

        $metadata = ExecutionMetadata::since($startedAt, $monotonicStart, $startMemory, $startCpuUsage);

        $this->assertSame(getmypid(), $metadata->getPid());
    }

    public function testLoadAveragesAreNonNegativeFloats(): void
    {
        [$startedAt, $monotonicStart, $startMemory, $startCpuUsage] = $this->startMeasuring();

        $metadata = ExecutionMetadata::since($startedAt, $monotonicStart, $startMemory, $startCpuUsage);

        $this->assertGreaterThanOrEqual(0.0, $metadata->getLoadAverage1Min());
        $this->assertGreaterThanOrEqual(0.0, $metadata->getLoadAverage5Min());
        $this->assertGreaterThanOrEqual(0.0, $metadata->getLoadAverage15Min());
    }

    public function testToArrayAndJsonSerializeMatch(): void
    {
        [$startedAt, $monotonicStart, $startMemory, $startCpuUsage] = $this->startMeasuring();

        $metadata = ExecutionMetadata::since($startedAt, $monotonicStart, $startMemory, $startCpuUsage);

        $this->assertSame($metadata->toArray(), $metadata->jsonSerialize());
        $this->assertSame(
            [
                'startedAt',
                'finishedAt',
                'timestamp',
                'realTime',
                'userTime',
                'systemTime',
                'memoryUsed',
                'peakMemory',
                'pid',
                'loadAverage1Min',
                'loadAverage5Min',
                'loadAverage15Min',
            ],
            array_keys($metadata->toArray())
        );
    }

    public function testMissingStartingCpuReadingLeavesOtherMetricsAvailable(): void
    {
        [$startedAt, $monotonicStart, $startMemory] = $this->startMeasuring();

        $metadata = ExecutionMetadata::since($startedAt, $monotonicStart, $startMemory, null);

        $this->assertNull($metadata->getUserTime());
        $this->assertNull($metadata->getSystemTime());
        $this->assertSame(getmypid(), $metadata->getPid());
        $this->assertNotNull($metadata->getLoadAverage1Min());
        $this->assertNotNull($metadata->getLoadAverage5Min());
        $this->assertNotNull($metadata->getLoadAverage15Min());
        $this->assertGreaterThanOrEqual(0.0, $metadata->getRealTime());
        $this->assertGreaterThan(0, $metadata->getPeakMemory());
        $this->assertNull($metadata->toArray()['userTime']);
        $this->assertNull($metadata->jsonSerialize()['systemTime']);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFailedFinalReadingsRemainNullInGettersAndJson(): void
    {
        [$startedAt, $monotonicStart, $startMemory, $startCpuUsage] = $this->startMeasuring();
        require __DIR__ . '/Fixture/SystemMetricFailures.php';
        SystemMetricFailures::$cpu = true;
        SystemMetricFailures::$pid = true;
        SystemMetricFailures::$load = true;

        $metadata = ExecutionMetadata::since($startedAt, $monotonicStart, $startMemory, $startCpuUsage);

        $this->assertNull($metadata->getUserTime());
        $this->assertNull($metadata->getSystemTime());
        $this->assertNull($metadata->getPid());
        $this->assertNull($metadata->getLoadAverage1Min());
        $this->assertNull($metadata->getLoadAverage5Min());
        $this->assertNull($metadata->getLoadAverage15Min());
        $this->assertGreaterThanOrEqual(0.0, $metadata->getRealTime());
        $this->assertSame($startedAt, $metadata->getStartedAt());
        $this->assertGreaterThan(0, $metadata->getPeakMemory());
        $this->assertSame($metadata->toArray(), $metadata->jsonSerialize());
        $json = json_encode($metadata, JSON_THROW_ON_ERROR);
        foreach (['userTime', 'systemTime', 'pid', 'loadAverage1Min', 'loadAverage5Min', 'loadAverage15Min'] as $key) {
            $this->assertStringContainsString('"' . $key . '":null', $json);
        }
    }
}
