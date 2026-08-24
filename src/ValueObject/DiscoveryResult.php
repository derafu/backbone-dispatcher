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

use Derafu\BackboneDispatcher\Contract\DiscoveryResultInterface;
use Derafu\BackboneDispatcher\Contract\ProblemDetailInterface;

/**
 * The outcome of one `SafeExplorerInterface` call: either the value
 * `ExplorerInterface` returned, or a `ProblemDetail` describing why it could
 * not be completed. Never both, and never neither.
 */
class DiscoveryResult implements DiscoveryResultInterface
{
    private function __construct(
        private readonly bool $success,
        private readonly mixed $value,
        private readonly ?ProblemDetailInterface $problem
    ) {
    }

    public static function success(mixed $value): self
    {
        return new self(success: true, value: $value, problem: null);
    }

    public static function failure(ProblemDetailInterface $problem): self
    {
        return new self(success: false, value: null, problem: $problem);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * {@inheritDoc}
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    public function getProblem(): ?ProblemDetailInterface
    {
        return $this->problem;
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'value' => $this->value,
            'problem' => $this->problem,
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
