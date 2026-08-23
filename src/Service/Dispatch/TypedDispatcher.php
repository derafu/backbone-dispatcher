<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\BackboneDispatcher\Service\Dispatch;

use Derafu\BackboneDispatcher\Contract\DirectDispatcherInterface;
use Derafu\BackboneDispatcher\Contract\OperationRequestInterface;
use Derafu\BackboneDispatcher\Contract\OperationResultInterface;
use Derafu\BackboneDispatcher\Contract\TypedDispatcherInterface;
use Derafu\BackboneDispatcher\ValueObject\OperationResult;

/**
 * Adapts `DirectDispatcherInterface` to work with `OperationRequestInterface`/
 * `OperationResultInterface` instead of loose arguments and a raw return
 * value.
 *
 * Does not resolve, invoke or serialize anything itself: all of that is
 * `DirectDispatcherInterface`'s job. This class only converts types on the
 * way in and on the way out. It does not catch exceptions either — that is
 * `SafeDispatcherInterface`'s job, which wraps this class rather than
 * duplicating it.
 */
class TypedDispatcher implements TypedDispatcherInterface
{
    public function __construct(
        private readonly DirectDispatcherInterface $directDispatcher,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function dispatch(OperationRequestInterface $request): OperationResultInterface
    {
        $value = $this->directDispatcher->dispatch(
            $request->getPackage(),
            $request->getComponent(),
            $request->getWorker(),
            $request->getOperation(),
            $request->getParameters()
        );

        return OperationResult::success($value);
    }
}
