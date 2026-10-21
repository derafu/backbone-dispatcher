<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsBackboneDispatcher\Fixture;

use Derafu\BackboneDispatcher\Contract\ExecutionMetadataInterface;
use Derafu\BackboneDispatcher\Contract\OperationResultInterface;
use Derafu\BackboneDispatcher\Contract\ProblemDetailInterface;

/**
 * Simulates a producer that omits the data type of a successful result.
 */
final class MissingDataTypeResult implements OperationResultInterface
{
    public function __construct(private readonly OperationResultInterface $result)
    {
    }

    public function isSuccess(): bool
    {
        return $this->result->isSuccess();
    }

    public function getValue(): mixed
    {
        return $this->result->getValue();
    }

    public function getProblem(): ?ProblemDetailInterface
    {
        return $this->result->getProblem();
    }

    public function getDataType(): null
    {
        return null;
    }

    public function getMetadata(): ExecutionMetadataInterface
    {
        return $this->result->getMetadata();
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return $this->result->toArray();
    }

    public function jsonSerialize(): mixed
    {
        return $this->result->jsonSerialize();
    }
}
