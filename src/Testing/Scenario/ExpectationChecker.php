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

use Derafu\BackboneDispatcher\Contract\SerializerInterface;
use Derafu\BackboneDispatcher\Exception\MissingOptionalDependencyException;
use Derafu\BackboneDispatcher\Testing\Exception\InvalidScenarioException;
use Exception;
use JmesPath\Env;
use Symfony\Component\Yaml\Tag\TaggedValue;
use Throwable;

/**
 * Compares what a step produced with what the scenario expects.
 *
 * It does not assert: it returns the list of differences, one text each, so
 * the test case decides how to report them (and so the "either" expectation
 * can try the two outcomes). An empty list means that the step is as expected.
 *
 * The values are compared with strict equality. `!any` matches any value that
 * is not null, `!instanceof` matches an object of a class, `!ref` is replaced
 * by the value it points to, and these can be nested inside a map or a list.
 * A map has to have the same keys in the same order.
 */
final class ExpectationChecker
{
    /**
     * @throws MissingOptionalDependencyException When `mtdowling/jmespath.php`
     * is not installed.
     */
    public function __construct(
        private readonly TagResolver $tags,
        private readonly SerializerInterface $serializer,
    ) {
        if (!class_exists(Env::class)) {
            throw MissingOptionalDependencyException::forFeature(self::class, 'mtdowling/jmespath.php');
        }
    }

    /**
     * Checks a step.
     *
     * @param Step $step
     * @param StepResult $result
     * @param array<string, array{value: mixed, problem: array{type: string, title: string, detail: string}|null}> $references
     * The results of the previous steps, by name of step.
     * @return list<string> The differences. Empty when the step is as expected.
     */
    public function check(Step $step, StepResult $result, array $references): array
    {
        $expectation = $step->expectation;

        return match ($expectation->kind) {
            ExpectationKind::None, ExpectationKind::Expect => $this->checkSuccess($step, $result, $references),
            ExpectationKind::Failure => $this->checkFailure($step, $result),
            ExpectationKind::Either => $result->success
                ? $this->checkSuccess($step, $result, $references)
                : $this->checkFailure($step, $result),
        };
    }

    /**
     * @param array<string, array{value: mixed, problem: array{type: string, title: string, detail: string}|null}> $references
     * @return list<string>
     */
    private function checkSuccess(Step $step, StepResult $result, array $references): array
    {
        if (!$result->success) {
            return [sprintf('Step %s failed and it had to succeed: %s', $step->name, $this->describeFailure($result))];
        }

        $differences = $this->checkType($step, $result);

        foreach ($step->expectation->expect as $expression => $expected) {
            $actual = $this->evaluate($result->value, (string) $expression);
            if ($actual instanceof NotEvaluable) {
                $differences[] = sprintf('Step %s: %s', $step->name, $actual->reason);
                continue;
            }
            $difference = $this->compare((string) $expression, $expected, $actual->value, $references);
            if ($difference !== null) {
                $differences[] = sprintf('Step %s: %s', $step->name, $difference);
            }
        }

        foreach ($step->expectation->call as $call => $expected) {
            $difference = $this->checkCall($result, (string) $call, $expected, $references);
            if ($difference !== null) {
                $differences[] = sprintf('Step %s: %s', $step->name, $difference);
            }
        }

        return $differences;
    }

    /**
     * @return list<string>
     */
    private function checkFailure(Step $step, StepResult $result): array
    {
        if ($result->success) {
            return [sprintf('Step %s succeeded and it had to fail.', $step->name)];
        }

        $failure = $step->expectation->failure;
        if ($failure === null) {
            return [];
        }

        $differences = [];

        if ($failure->exceptions !== []) {
            $actual = $result->throwable !== null ? $result->throwable::class : ($result->problem['title'] ?? '');
            $matches = false;
            foreach ($failure->exceptions as $class) {
                $matches = $matches || $actual === $class || is_a($actual, $class, true);
            }
            if (!$matches) {
                $differences[] = sprintf(
                    'Step %s failed with %s, expected %s.',
                    $step->name,
                    $actual,
                    implode(' or ', $failure->exceptions)
                );
            }
        }

        if ($failure->causedBy !== null && !$this->hasInChain($result->throwable, $failure->causedBy)) {
            $differences[] = sprintf('Step %s failed but %s is not in its chain of exceptions.', $step->name, $failure->causedBy);
        }

        if ($failure->message !== null) {
            $message = $result->throwable !== null ? $result->throwable->getMessage() : ($result->problem['detail'] ?? '');
            if (!str_contains($message, $failure->message)) {
                $differences[] = sprintf(
                    'Step %s failed with the message "%s", expected one that contains "%s".',
                    $step->name,
                    $message,
                    $failure->message
                );
            }
        }

        return $differences;
    }

    /**
     * A deserialize step has to build an instance of its type.
     *
     * @return list<string>
     */
    private function checkType(Step $step, StepResult $result): array
    {
        if ($step->kind !== StepKind::Deserialize) {
            return [];
        }

        foreach (explode('|', $step->target) as $candidate) {
            $candidate = trim($candidate, " \t?");
            if ($result->object !== null && is_a($result->object, $candidate)) {
                return [];
            }
            if ($result->object === null && $candidate === 'array' && is_array($result->value)) {
                return [];
            }
        }

        return [sprintf(
            'Step %s built %s, expected an instance of %s.',
            $step->name,
            get_debug_type($result->object ?? $result->value),
            $step->target
        )];
    }

    /**
     * @param array<string, array{value: mixed, problem: array{type: string, title: string, detail: string}|null}> $references
     */
    private function checkCall(StepResult $result, string $call, mixed $expected, array $references): ?string
    {
        if ($result->object === null || preg_match(ScenarioLoader::CALL, $call, $parts) !== 1) {
            return sprintf('%s: there is no object to call it on.', $call);
        }

        $method = $parts[1];
        if (!is_callable([$result->object, $method])) {
            return sprintf('%s: %s has no public method %s().', $call, $result->object::class, $method);
        }

        $returned = $result->object->{$method}();

        if (isset($parts[2])) {
            $actual = $this->evaluate($this->serializer->serialize($returned), $parts[2]);
            if ($actual instanceof NotEvaluable) {
                return sprintf('%s: %s', $call, $actual->reason);
            }

            return $this->compare($call, $expected, $actual->value, $references);
        }

        $isTag = $expected instanceof TaggedValue && $expected->getTag() === 'instanceof';

        return $this->compare(
            $call,
            $expected,
            is_object($returned) && !$isTag ? $this->serializer->serialize($returned) : $returned,
            $references
        );
    }

    /**
     * Evaluates a JMESPath expression over a serialized value, as it is: an
     * empty array is an empty array and a one-element array is a list. The
     * expression `@` is the whole value, whatever it is.
     *
     * @throws InvalidScenarioException When the expression is not valid.
     */
    private function evaluate(mixed $value, string $expression): Evaluated|NotEvaluable
    {
        if ($expression === '@') {
            return new Evaluated($value);
        }

        if (!is_array($value)) {
            return new NotEvaluable(sprintf(
                'the value is %s, an array is needed to evaluate "%s".',
                get_debug_type($value),
                $expression
            ));
        }

        try {
            return new Evaluated(Env::search($expression, $value));
        } catch (Exception $e) {
            throw InvalidScenarioException::forExpression($expression, $e);
        }
    }

    /**
     * @param array<string, array{value: mixed, problem: array{type: string, title: string, detail: string}|null}> $references
     */
    private function compare(string $label, mixed $expected, mixed $actual, array $references): ?string
    {
        return $this->matches($expected, $actual, $references)
            ? null
            : sprintf('%s: expected %s, got %s.', $label, $this->export($expected), $this->export($actual));
    }

    /**
     * @param array<string, array{value: mixed, problem: array{type: string, title: string, detail: string}|null}> $references
     */
    private function matches(mixed $expected, mixed $actual, array $references): bool
    {
        if ($expected instanceof TaggedValue) {
            return match ($expected->getTag()) {
                'any' => $actual !== null,
                'instanceof' => is_string($expected->getValue())
                    && is_object($actual)
                    && is_a($actual, $expected->getValue()),
                default => $this->matches($this->tags->resolve($expected, $references), $actual, $references),
            };
        }

        if (is_array($expected)) {
            if (!is_array($actual) || array_keys($expected) !== array_keys($actual)) {
                return false;
            }
            foreach ($expected as $key => $item) {
                if (!$this->matches($item, $actual[$key], $references)) {
                    return false;
                }
            }

            return true;
        }

        return $expected === $actual;
    }

    private function hasInChain(?Throwable $throwable, string $class): bool
    {
        for ($current = $throwable; $current !== null; $current = $current->getPrevious()) {
            if (is_a($current, $class)) {
                return true;
            }
        }

        return false;
    }

    private function describeFailure(StepResult $result): string
    {
        if ($result->throwable !== null) {
            return $result->throwable::class . ': ' . $result->throwable->getMessage();
        }

        return ($result->problem['title'] ?? '') . ': ' . ($result->problem['detail'] ?? '');
    }

    private function export(mixed $value): string
    {
        if ($value instanceof TaggedValue) {
            $tagged = $value->getValue();

            return '!' . $value->getTag() . match (true) {
                $tagged === '' || $tagged === null => '',
                is_string($tagged) => ' ' . $tagged,
                default => ' ' . $this->export($tagged),
            };
        }

        if (is_object($value)) {
            return $value::class;
        }

        if (is_float($value)) {
            return var_export($value, true);
        }

        $text = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        $text = $text === false ? get_debug_type($value) : $text;

        return mb_strlen($text) > 200 ? mb_substr($text, 0, 200) . '...' : $text;
    }
}
