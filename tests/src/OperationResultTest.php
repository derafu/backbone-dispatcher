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
use Derafu\BackboneDispatcher\ValueObject\OperationResult;
use Derafu\BackboneDispatcher\ValueObject\ProblemDetail;
use Derafu\BackboneDispatcher\ValueObject\SafeThrowable;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleGreeting;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(OperationResult::class)]
#[UsesClass(ExecutionMetadata::class)]
#[UsesClass(ProblemDetail::class)]
#[UsesClass(SafeThrowable::class)]
class OperationResultTest extends TestCase
{
    /** @return array<string, array{mixed, string}> */
    public static function successfulValues(): array
    {
        return [
            'null is a successful value' => [null, 'NULL'],
            'false is a successful value' => [false, 'boolean'],
            'zero is a successful value' => [0, 'integer'],
            'array' => [['name' => 'example'], 'array'],
            'domain object stays intact' => [new ExampleGreeting('Hello'), ExampleGreeting::class],
        ];
    }

    #[DataProvider('successfulValues')]
    public function testSuccessPreservesItsValueTypeAndMeasuredMetadata(mixed $value, string $type): void
    {
        $clock = hrtime(true);
        $this->assertIsInt($clock);
        $metadata = ExecutionMetadata::since(date(DATE_ATOM), $clock, memory_get_usage(true), null);
        $result = OperationResult::success($value, $metadata, $type);

        $this->assertTrue($result->isSuccess());
        $this->assertSame($value, $result->getValue());
        $this->assertSame($type, $result->getDataType());
        $this->assertSame($metadata, $result->getMetadata());
        $this->assertNull($result->getProblem());
        $this->assertSame(['success' => true, 'value' => $value, 'problem' => null, 'metadata' => $metadata, 'dataType' => $type], $result->toArray());
        $this->assertSame($result->toArray(), $result->jsonSerialize());
        $json = json_decode(json_encode($result, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($json);
        $this->assertTrue($json['success']);
        $this->assertSame($type, $json['dataType']);
    }

    public function testFailurePreservesItsProblemAndMetadataWithoutAValueOrDataType(): void
    {
        $clock = hrtime(true);
        $this->assertIsInt($clock);
        $metadata = ExecutionMetadata::since(date(DATE_ATOM), $clock, memory_get_usage(true), null);
        $problem = new ProblemDetail('Operation failed.', SafeThrowable::fromThrowable(new RuntimeException('Original error.')), $metadata->getTimestamp(), 'test');
        $result = OperationResult::failure($problem, $metadata);

        $this->assertFalse($result->isSuccess());
        $this->assertNull($result->getValue());
        $this->assertNull($result->getDataType());
        $this->assertSame($problem, $result->getProblem());
        $this->assertSame($metadata, $result->getMetadata());
        $this->assertSame(['success' => false, 'value' => null, 'problem' => $problem, 'metadata' => $metadata, 'dataType' => null], $result->toArray());
        $this->assertSame($result->toArray(), $result->jsonSerialize());
    }
}
