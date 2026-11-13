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
use Derafu\BackboneDispatcher\Service\Discovery\Explorer;
use Derafu\BackboneDispatcher\Service\Discovery\SafeExplorer;
use Derafu\BackboneDispatcher\Service\Reflection\Inspector;
use Derafu\BackboneDispatcher\Testing\DispatcherConformanceTestCase;

/**
 * The conformance suite over a dispatcher whose explorer fails. It is not run
 * by PHPUnit (its name does not end in `Test`).
 */
class BrokenExplorerConformance extends DispatcherConformanceTestCase
{
    protected static function explorer(): SafeExplorerInterface
    {
        return new SafeExplorer(new Explorer(new ThrowingPackageRegistry(), new Inspector()), environment: 'test', debug: true);
    }

    protected static function objectFactory(): ObjectFactoryInterface
    {
        return HarnessServices::objectFactory();
    }
}
