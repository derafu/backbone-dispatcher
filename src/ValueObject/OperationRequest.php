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

use Derafu\BackboneDispatcher\Contract\OperationRequestInterface;
use Derafu\BackboneDispatcher\Exception\InvalidOperationIdException;

/**
 * Everything needed to dispatch a single operation: which package,
 * component and worker own it, the operation's own name, and the
 * parameters to call it with.
 *
 * Unlike `Derafu\BackboneApi\ValueObject\RouteMatch` (which represents a
 * partially parsed URL and so allows null segments), every field here is
 * required: there is no such thing as dispatching an incomplete request.
 */
class OperationRequest implements OperationRequestInterface
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        private readonly string $package,
        private readonly string $component,
        private readonly string $worker,
        private readonly string $operation,
        private readonly array $parameters = []
    ) {
    }

    public function getPackage(): string
    {
        return $this->package;
    }

    public function getComponent(): string
    {
        return $this->component;
    }

    public function getWorker(): string
    {
        return $this->worker;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * The `"package.component.worker::operation"` identifier for this
     * request, e.g. for use as a `ProblemDetail::getInstance()` value.
     *
     * `::` (not a single `:`) on purpose: `derafu/backbone` already uses a
     * single `:` for its own `.job:name`/`.handler:name`/`.strategy:name`
     * ids — this identifier is a dispatcher-only concept, unrelated to
     * that family, and `::` keeps it visually distinct instead of looking
     * like a fourth member of it.
     *
     * @return string
     */
    public function getId(): string
    {
        return sprintf(
            '%s.%s.%s::%s',
            $this->package,
            $this->component,
            $this->worker,
            $this->operation
        );
    }

    /**
     * Builds an `OperationRequest` from its `"package.component.worker::operation"`
     * identifier plus the parameters to call it with — the shape a caller
     * on the other side of a boundary (e.g. the Python bridge) would
     * naturally send: one id string, one parameters bag.
     *
     * @param string $id
     * @param array<string, mixed> $parameters
     * @return self
     */
    public static function fromId(string $id, array $parameters = []): self
    {
        $parts = explode('::', $id, 2);
        if (count($parts) !== 2) {
            throw InvalidOperationIdException::forId($id);
        }
        [$path, $operation] = $parts;

        $segments = explode('.', $path);
        if (count($segments) !== 3) {
            throw InvalidOperationIdException::forId($id);
        }
        [$package, $component, $worker] = $segments;

        if ($package === '' || $component === '' || $worker === '' || $operation === '') {
            throw InvalidOperationIdException::forId($id);
        }

        return new self($package, $component, $worker, $operation, $parameters);
    }
}
