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

use Derafu\BackboneDispatcher\Contract\OperationResultInterface;
use Derafu\BackboneDispatcher\Contract\ProblemDetailInterface;

/**
 * The outcome of dispatching a single `OperationRequest`: either the value
 * the operation returned, or a `ProblemDetail` describing why it could not
 * be completed. Never both, and never neither.
 */
class OperationResult implements OperationResultInterface
{
    private function __construct(
        private readonly bool $success,
        private readonly mixed $value,
        private readonly ?ProblemDetailInterface $problem
    ) {
    }

    /**
     * @param mixed $value The value returned by the operation, exactly as
     * the worker produced it.
     *
     * If this result will be turned into JSON (via `jsonSerialize()`/
     * `toArray()`) — e.g. because it is about to cross a language or
     * process boundary — `$value` must already be something safely
     * serializable, typically the output of
     * `SerializerInterface::serialize()`. This class does not serialize
     * `$value` itself: that would duplicate what `Serializer` already
     * does elsewhere in this package.
     *
     * If this result stays in-process instead (e.g. a PHP-only caller
     * reads `getValue()` directly and keeps working with the real object,
     * never encoding it), `$value` can be exactly what the worker
     * returned — there is no need to serialize it up front for that case.
     */
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
     * @return mixed The value returned by the operation. `null` if this
     * result is a failure (use `isSuccess()` to tell the two apart, not
     * this method returning `null`, since `null` is also a valid
     * successful value).
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
