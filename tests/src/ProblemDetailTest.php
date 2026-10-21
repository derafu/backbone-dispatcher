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

use Derafu\BackboneDispatcher\ValueObject\ProblemDetail;
use Derafu\BackboneDispatcher\ValueObject\SafeThrowable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ProblemDetail::class)]
#[UsesClass(SafeThrowable::class)]
class ProblemDetailTest extends TestCase
{
    #[TestWith([true])]
    #[TestWith([false])]
    public function testDebugControlsExceptionExposureInJsonAndText(bool $debug): void
    {
        $throwable = SafeThrowable::fromThrowable(new RuntimeException('Internal diagnostic.'));
        $context = ['document' => 'invoice', 'attempt' => 2];
        $problem = new ProblemDetail('The document could not be processed.', $throwable, 1234.5, 'test', type: 'urn:problem:document', title: 'Document error', instance: 'p.c.w::run', context: $context, debug: $debug);

        $this->assertSame('urn:problem:document', $problem->getType());
        $this->assertSame('Document error', $problem->getTitle());
        $this->assertSame('The document could not be processed.', $problem->getDetail());
        $this->assertSame('p.c.w::run', $problem->getInstance());
        $this->assertSame($throwable, $problem->getThrowable());
        $this->assertSame($context, $problem->getContext());
        $this->assertSame(1234.5, $problem->getTimestamp());
        $this->assertSame('test', $problem->getEnvironment());
        $this->assertSame($debug, $problem->isDebug());
        $data = $problem->toArray();
        $this->assertSame($problem->toArray(), $problem->jsonSerialize());
        $this->assertSame($debug ? $throwable : null, $data['extensions']['throwable']);
        $this->assertSame($context, $data['extensions']['context']);
        $json = json_decode(json_encode($problem, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($json);
        $this->assertSame('Document error', $json['title']);
        $extensions = $json['extensions'];
        $this->assertIsArray($extensions);
        $this->assertNull($extensions['data_type']);
        $text = (string) $problem;
        $this->assertStringContainsString('## Context', $text);
        $this->assertStringContainsString('invoice', $text);
        $this->assertStringContainsString('The document could not be processed.', $text);
        if ($debug) {
            $this->assertStringContainsString('## Throwable', $text);
            $this->assertStringContainsString('Internal diagnostic.', $text);
        } else {
            $this->assertStringNotContainsString('## Throwable', $text);
            $this->assertStringNotContainsString('Internal diagnostic.', $text);
            $this->assertStringNotContainsString('Internal diagnostic.', json_encode($problem, JSON_THROW_ON_ERROR));
        }
    }

    #[TestWith([null, 'RuntimeException'])]
    #[TestWith(['Custom title', 'Custom title'])]
    #[TestWith(['', ''])]
    public function testOnlyAnAbsentTitleFallsBackToTheThrowableClass(?string $title, string $expected): void
    {
        $problem = new ProblemDetail('Failure.', SafeThrowable::fromThrowable(new RuntimeException('Error.')), 1234.5, 'test', title: $title, debug: false);

        $this->assertSame($expected, $problem->getTitle());
        $this->assertSame('about:blank', $problem->getType());
        $this->assertNull($problem->getInstance());
        $this->assertSame([], $problem->getContext());
        $this->assertStringNotContainsString('## Context', (string) $problem);
    }
}
