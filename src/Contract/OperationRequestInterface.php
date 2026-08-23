<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\BackboneDispatcher\Contract;

/**
 * Everything needed to dispatch a single operation: which package,
 * component and worker own it, the operation's own name, and the
 * parameters to call it with.
 */
interface OperationRequestInterface
{
    public function getPackage(): string;

    public function getComponent(): string;

    public function getWorker(): string;

    public function getOperation(): string;

    /**
     * @return array<string, mixed>
     */
    public function getParameters(): array;

    /**
     * The `"package.component.worker::operation"` identifier for this
     * request, e.g. for use as a `ProblemDetailInterface::getInstance()`
     * value.
     *
     * @return string
     */
    public function getId(): string;
}
