<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

// Load only in isolated tests: simulate failed native reads without changing
// the host system or the production API.

namespace Derafu\TestsBackboneDispatcher\Fixture;

class SystemMetricFailures
{
    public static bool $cpu = false;

    public static bool $pid = false;

    public static bool $load = false;
}

namespace Derafu\BackboneDispatcher\ValueObject;

use Derafu\TestsBackboneDispatcher\Fixture\SystemMetricFailures;

/**
 * @return array<string, int>|false
 */
function getrusage(int $mode = 0): array|false
{
    return SystemMetricFailures::$cpu ? false : \getrusage($mode);
}

/**
 * @return array{float, float, float}|false
 */
function sys_getloadavg(): array|false
{
    return SystemMetricFailures::$load ? false : \sys_getloadavg();
}

function getmypid(): int|false
{
    return SystemMetricFailures::$pid ? false : \getmypid();
}

namespace Derafu\BackboneDispatcher\Service\Dispatch;

use Derafu\TestsBackboneDispatcher\Fixture\SystemMetricFailures;

/**
 * @return array<string, int>|false
 */
function getrusage(int $mode = 0): array|false
{
    return SystemMetricFailures::$cpu ? false : \getrusage($mode);
}
