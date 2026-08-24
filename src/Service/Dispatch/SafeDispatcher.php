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

use Derafu\BackboneDispatcher\Contract\ExecutionMetadataInterface;
use Derafu\BackboneDispatcher\Contract\OperationRequestInterface;
use Derafu\BackboneDispatcher\Contract\OperationResultInterface;
use Derafu\BackboneDispatcher\Contract\ProblemDetailInterface;
use Derafu\BackboneDispatcher\Contract\SafeDispatcherInterface;
use Derafu\BackboneDispatcher\Contract\SerializerInterface;
use Derafu\BackboneDispatcher\Contract\TypedDispatcherInterface;
use Derafu\BackboneDispatcher\ValueObject\ExecutionMetadata;
use Derafu\BackboneDispatcher\ValueObject\OperationResult;
use Derafu\BackboneDispatcher\ValueObject\ProblemDetail;
use Derafu\BackboneDispatcher\ValueObject\SafeThrowable;
use Throwable;

/**
 * Wraps `TypedDispatcherInterface` so that:
 *
 *   - No `Throwable` ever crosses back to the caller uncaught: every
 *     dispatch either returns a successful `OperationResultInterface` or a
 *     failure one, built from whatever was thrown.
 *   - The successful value is passed through `SerializerInterface` before
 *     being wrapped, since this is the tier that actually exists for
 *     handing the result across a language or process boundary (e.g. to
 *     Python via `phpy`), unlike `DirectDispatcherInterface`/
 *     `TypedDispatcherInterface`, which return the value exactly as the
 *     operation produced it.
 *
 * Wraps `TypedDispatcherInterface`, not `DirectDispatcherInterface`
 * directly, so it does not have to repeat the request-unpacking that
 * `TypedDispatcherInterface` already does.
 */
class SafeDispatcher implements SafeDispatcherInterface
{
    public function __construct(
        private readonly TypedDispatcherInterface $typedDispatcher,
        private readonly SerializerInterface $serializer,
        private readonly string $environment = 'prod',
        private readonly bool $debug = false,
        private readonly ?string $projectDir = null,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function dispatch(OperationRequestInterface $request): OperationResultInterface
    {
        $startedAt = date(DATE_ATOM);
        $monotonicStart = hrtime(true);
        $startMemory = memory_get_usage(true);
        $startCpuUsage = getrusage();

        try {
            $result = $this->typedDispatcher->dispatch($request);
            $value = $this->serializer->serialize($result->getValue());
            $metadata = ExecutionMetadata::since($startedAt, $monotonicStart, $startMemory, $startCpuUsage);

            return OperationResult::success($value, $metadata, $result->getDataType());
        } catch (Throwable $e) {
            $metadata = ExecutionMetadata::since($startedAt, $monotonicStart, $startMemory, $startCpuUsage);

            return OperationResult::failure($this->buildProblem($e, $request, $metadata), $metadata);
        }
    }

    private function buildProblem(
        Throwable $e,
        OperationRequestInterface $request,
        ExecutionMetadataInterface $metadata
    ): ProblemDetailInterface {
        return new ProblemDetail(
            detail: $e->getMessage(),
            throwable: SafeThrowable::fromThrowable($e, $this->projectDir),
            timestamp: $metadata->getTimestamp(),
            environment: $this->environment,
            instance: $request->getId(),
            debug: $this->debug,
        );
    }
}
