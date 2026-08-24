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

use Derafu\BackboneDispatcher\ValueObject\DiscoveryResult;
use Derafu\BackboneDispatcher\ValueObject\ProblemDetail;
use Derafu\BackboneDispatcher\ValueObject\SafeThrowable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(DiscoveryResult::class)]
#[UsesClass(ProblemDetail::class)]
#[UsesClass(SafeThrowable::class)]
class DiscoveryResultTest extends TestCase
{
    public function testSuccessCarriesTheValueAndNoProblem(): void
    {
        $result = DiscoveryResult::success(['id' => 'example_package']);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(['id' => 'example_package'], $result->getValue());
        $this->assertNull($result->getProblem());
    }

    public function testFailureCarriesTheProblemAndNoValue(): void
    {
        $problem = new ProblemDetail(
            detail: 'The discovery id a.b.c.d is not valid.',
            throwable: SafeThrowable::fromThrowable(new RuntimeException('boom')),
            timestamp: microtime(true),
            environment: 'test',
            instance: 'a.b.c.d',
        );

        $result = DiscoveryResult::failure($problem);

        $this->assertFalse($result->isSuccess());
        $this->assertNull($result->getValue());
        $this->assertSame($problem, $result->getProblem());
    }

    public function testToArrayAndJsonSerializeMatch(): void
    {
        $result = DiscoveryResult::success('example_package');

        $this->assertSame($result->toArray(), $result->jsonSerialize());
        $this->assertSame([
            'success' => true,
            'value' => 'example_package',
            'problem' => null,
        ], $result->toArray());
    }
}
