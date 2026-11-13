<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\BackboneDispatcher\Testing\Scenario;

use Derafu\BackboneDispatcher\Contract\ObjectFactoryInterface;
use Derafu\BackboneDispatcher\Contract\SafeDispatcherInterface;
use Derafu\BackboneDispatcher\Contract\SerializerInterface;
use Derafu\BackboneDispatcher\Testing\Exception\FixtureUnavailableException;
use Derafu\BackboneDispatcher\Testing\Exception\InvalidScenarioException;
use Derafu\BackboneDispatcher\ValueObject\OperationRequest;
use Derafu\Value\ValueType;
use Throwable;

/**
 * Runs the steps of a scenario against the real services of the dispatcher.
 *
 * It does not build any service: the dispatcher that is tested hands them in,
 * so the steps go through exactly what the application uses. It only runs: the
 * comparison with what the step expects is `ExpectationChecker`'s job.
 */
final class ScenarioRunner
{
    public function __construct(
        private readonly SafeDispatcherInterface $dispatcher,
        private readonly ObjectFactoryInterface $objectFactory,
        private readonly SerializerInterface $serializer,
        private readonly TagResolver $tags,
    ) {
    }

    /**
     * Runs a step.
     *
     * @param Step $step
     * @param array<string, array{value: mixed, problem: array{type: string, title: string, detail: string}|null}> $references
     * The results of the previous steps, by name of step: what `!ref` reads.
     * @return StepResult
     * @throws InvalidScenarioException When a fixture is not registered, or a
     * reference points to something that the results do not have.
     * @throws FixtureUnavailableException When a fixture needs a requirement
     * that is not available.
     */
    public function run(Step $step, array $references): StepResult
    {
        $input = $this->tags->resolve($step->input, $references);

        return $step->kind === StepKind::Dispatch
            ? $this->dispatch($step, $input)
            : $this->deserialize($step, $input);
    }

    private function dispatch(Step $step, mixed $input): StepResult
    {
        $parameters = ValueType::map()->parse($input, $step->name . '.parameters', self::class);

        $result = $this->dispatcher->dispatch(OperationRequest::fromId($step->target, $parameters));
        if ($result->isSuccess()) {
            return new StepResult($step->name, true, $result->getValue());
        }

        $problem = $result->getProblem();

        return new StepResult($step->name, false, problem: [
            'type' => $problem?->getType() ?? '',
            'title' => $problem?->getTitle() ?? '',
            'detail' => $problem?->getDetail() ?? '',
        ]);
    }

    private function deserialize(Step $step, mixed $input): StepResult
    {
        $data = ValueType::oneOf(ValueType::string(), ValueType::map())
            ->parse($input, $step->name . '.data', self::class);

        try {
            $created = $this->objectFactory->create($data, $step->target);
        } catch (Throwable $e) {
            return new StepResult($step->name, false, throwable: $e);
        }

        return new StepResult(
            $step->name,
            true,
            $this->serializer->serialize($created),
            is_object($created) ? $created : null,
        );
    }
}
