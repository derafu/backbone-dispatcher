<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsBackboneDispatcher\Fixture\Harness;

use Derafu\BackboneDispatcher\Contract\ObjectFactoryInterface;
use Derafu\BackboneDispatcher\Contract\SafeExplorerInterface;
use Derafu\BackboneDispatcher\Testing\DispatcherConformanceTestCase;

/**
 * The conformance suite over a dispatcher that breaks every rule. It is not
 * run by PHPUnit (its name does not end in `Test`): `ConformanceTestCaseTest`
 * calls its checks and expects them to fail.
 */
class BadConformance extends DispatcherConformanceTestCase
{
    protected static function explorer(): SafeExplorerInterface
    {
        return HarnessServices::badExplorer();
    }

    protected static function objectFactory(): ObjectFactoryInterface
    {
        return HarnessServices::emptyObjectFactory();
    }

    protected function parameterNamePattern(): ?string
    {
        return '/^[a-z][a-z0-9]*(_[a-z0-9]+)*$/';
    }
}
