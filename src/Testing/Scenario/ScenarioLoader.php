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

use Derafu\BackboneDispatcher\Exception\MissingOptionalDependencyException;
use Derafu\BackboneDispatcher\Testing\Exception\InvalidScenarioException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Tag\TaggedValue;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads a scenario file and checks its structure.
 *
 * The values are kept as the file has them, with `TaggedValue` objects where
 * there is a tag: `ScenarioRunner` resolves `!fixture` and `!ref` when it runs
 * the step, and `ExpectationChecker` evaluates `!any` and `!instanceof`. What
 * the loader checks is that every tag is known, is in a place where it is
 * allowed and has a valid value, and that every `!ref` points to a step that
 * comes before the one that uses it.
 *
 * The format is described in the page "Testing" of the documentation of the
 * package.
 */
final class ScenarioLoader
{
    /**
     * Pattern of the name of a step.
     */
    private const STEP_NAME = '/^[a-z][a-z0-9_]*$/';

    /**
     * Pattern of a key of `call`: `method()` or `method().expression`.
     */
    public const CALL = '/^(\w+)\(\)(?:\.(.+))?$/';

    /**
     * Reads a scenario file.
     *
     * @param string $path The path of the YAML file.
     * @return Scenario
     * @throws InvalidScenarioException When the file is not a valid scenario.
     * @throws MissingOptionalDependencyException When `symfony/yaml` is not
     * installed.
     */
    public function load(string $path): Scenario
    {
        if (!class_exists(Yaml::class)) {
            throw MissingOptionalDependencyException::forFeature(self::class . '::load()', 'symfony/yaml');
        }

        try {
            $raw = Yaml::parseFile($path, Yaml::PARSE_CUSTOM_TAGS);
        } catch (ParseException) {
            throw InvalidScenarioException::forKey($path, '$', 'a valid YAML document');
        }

        $data = $this->map($raw, $path, '$', 'a map');
        $this->onlyKeys($data, ['description', 'requires', 'steps'], $path, '');

        return new Scenario(
            $path,
            $this->nonEmptyString($data['description'] ?? null, $path, 'description'),
            $this->requires($data['requires'] ?? [], $path),
            $this->steps($data['steps'] ?? null, $path),
        );
    }

    /**
     * @return list<string>
     */
    private function requires(mixed $value, string $path): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw InvalidScenarioException::forKey($path, 'requires', 'a list of names');
        }

        $requires = [];
        foreach ($value as $i => $name) {
            $requires[] = $this->nonEmptyString($name, $path, 'requires.' . $i);
        }

        return $requires;
    }

    /**
     * @return array<string, Step>
     */
    private function steps(mixed $value, string $path): array
    {
        if (!is_array($value) || $value === [] || array_is_list($value)) {
            throw InvalidScenarioException::forKey($path, 'steps', 'a map of one step or more');
        }

        $steps = [];
        foreach ($value as $name => $rawStep) {
            $name = (string) $name;
            if (preg_match(self::STEP_NAME, $name) !== 1) {
                throw InvalidScenarioException::forStepName($path, $name);
            }

            $steps[$name] = $this->step($name, $rawStep, $path, array_keys($steps));
        }

        return $steps;
    }

    /**
     * @param list<string> $previous The names of the steps that come before.
     */
    private function step(string $name, mixed $value, string $path, array $previous): Step
    {
        $key = 'steps.' . $name;
        $data = $this->map($value, $path, $key, 'a map');
        $this->onlyKeys($data, ['dispatch', 'deserialize', 'expect', 'failure', 'either'], $path, $key);

        $kinds = array_values(array_filter(
            [StepKind::Dispatch, StepKind::Deserialize],
            static fn (StepKind $kind): bool => array_key_exists($kind->value, $data)
        ));
        if (count($kinds) !== 1) {
            throw InvalidScenarioException::forStepKind($path, $name);
        }
        $kind = $kinds[0];

        if ($kind === StepKind::Dispatch) {
            [$target, $input] = $this->dispatch($data['dispatch'], $path, $key . '.dispatch', $previous);
        } else {
            [$target, $input] = $this->deserialize($data['deserialize'], $path, $key . '.deserialize', $previous);
        }

        return new Step(
            $name,
            $kind,
            $target,
            $input,
            $this->expectation($data, $kind, $name, $path, $key, $previous),
        );
    }

    /**
     * @param list<string> $previous
     * @return array{string, array<string, mixed>}
     */
    private function dispatch(mixed $value, string $path, string $key, array $previous): array
    {
        $data = $this->map($value, $path, $key, 'a map');
        $this->onlyKeys($data, ['operation', 'parameters'], $path, $key);

        $parameters = $this->map($data['parameters'] ?? [], $path, $key . '.parameters', 'a map');
        $this->walkTags($parameters, $path, $key . '.parameters', ['fixture', 'ref'], $previous);

        return [$this->nonEmptyString($data['operation'] ?? null, $path, $key . '.operation'), $parameters];
    }

    /**
     * @param list<string> $previous
     * @return array{string, mixed}
     */
    private function deserialize(mixed $value, string $path, string $key, array $previous): array
    {
        $data = $this->map($value, $path, $key, 'a map');
        $this->onlyKeys($data, ['type', 'data'], $path, $key);

        $input = $data['data'] ?? null;
        if (!is_string($input) && !is_array($input) && !$input instanceof TaggedValue) {
            throw InvalidScenarioException::forKey($path, $key . '.data', 'a string, a map or a tag');
        }
        $this->walkTags($input, $path, $key . '.data', ['fixture', 'ref'], $previous);

        return [$this->nonEmptyString($data['type'] ?? null, $path, $key . '.type'), $input];
    }

    /**
     * @param array<string, mixed> $data The step.
     * @param list<string> $previous
     */
    private function expectation(
        array $data,
        StepKind $kind,
        string $name,
        string $path,
        string $key,
        array $previous
    ): Expectation {
        $present = array_values(array_filter(
            ['expect', 'failure', 'either'],
            static fn (string $k): bool => array_key_exists($k, $data)
        ));
        if (count($present) > 1) {
            throw InvalidScenarioException::forExpectationKind($path, $name);
        }

        if ($present === []) {
            return new Expectation();
        }

        if ($present[0] === 'expect') {
            [$expect, $call] = $this->expect($data['expect'], $kind, $path, $key . '.expect', $previous);

            return new Expectation(ExpectationKind::Expect, $expect, $call);
        }

        if ($present[0] === 'failure') {
            return new Expectation(
                ExpectationKind::Failure,
                failure: $this->failure($data['failure'], $kind, $path, $key . '.failure')
            );
        }

        $either = $this->map($data['either'], $path, $key . '.either', 'a map');
        $this->onlyKeys($either, ['expect', 'failure'], $path, $key . '.either');
        foreach (['expect', 'failure'] as $required) {
            if (!array_key_exists($required, $either)) {
                throw InvalidScenarioException::forKey($path, $key . '.either.' . $required, 'present');
            }
        }
        [$expect, $call] = $this->expect($either['expect'], $kind, $path, $key . '.either.expect', $previous);

        return new Expectation(
            ExpectationKind::Either,
            $expect,
            $call,
            $this->failure($either['failure'], $kind, $path, $key . '.either.failure')
        );
    }

    /**
     * @param list<string> $previous
     * @return array{array<string, mixed>, array<string, mixed>}
     */
    private function expect(mixed $value, StepKind $kind, string $path, string $key, array $previous): array
    {
        $expect = $this->map($value, $path, $key, 'a map');
        if ($expect === []) {
            throw InvalidScenarioException::forKey($path, $key, 'a map of one expectation or more');
        }

        $call = [];
        if (array_key_exists('call', $expect)) {
            if ($kind !== StepKind::Deserialize) {
                throw InvalidScenarioException::forKindOnlyKey($path, $key . '.call', StepKind::Deserialize->value);
            }
            $call = $this->map($expect['call'], $path, $key . '.call', 'a map');
            unset($expect['call']);
            foreach (array_keys($call) as $method) {
                if (preg_match(self::CALL, $method) !== 1) {
                    throw InvalidScenarioException::forKey(
                        $path,
                        $key . '.call.' . $method,
                        'a method call like getName() or getName().expression'
                    );
                }
            }
            $this->walkTags($call, $path, $key . '.call', ['any', 'ref', 'instanceof'], $previous);
        }

        $this->walkTags($expect, $path, $key, ['any', 'ref'], $previous);

        return [$expect, $call];
    }

    private function failure(mixed $value, StepKind $kind, string $path, string $key): Failure
    {
        $data = $this->map($value, $path, $key, 'a map');
        $this->onlyKeys($data, ['exception', 'caused_by', 'message'], $path, $key);

        $exceptions = [];
        if (array_key_exists('exception', $data)) {
            $names = is_array($data['exception']) ? $data['exception'] : [$data['exception']];
            if ($names === [] || !array_is_list($names)) {
                throw InvalidScenarioException::forKey($path, $key . '.exception', 'a class name or a list of them');
            }
            foreach ($names as $i => $name) {
                $exceptions[] = $this->nonEmptyString($name, $path, $key . '.exception.' . $i);
            }
        }

        $causedBy = null;
        if (array_key_exists('caused_by', $data)) {
            if ($kind !== StepKind::Deserialize) {
                throw InvalidScenarioException::forKindOnlyKey($path, $key . '.caused_by', StepKind::Deserialize->value);
            }
            $causedBy = $this->nonEmptyString($data['caused_by'], $path, $key . '.caused_by');
        }

        $message = array_key_exists('message', $data)
            ? $this->nonEmptyString($data['message'], $path, $key . '.message')
            : null;

        if ($exceptions === [] && $causedBy === null && $message === null) {
            throw InvalidScenarioException::forKey($path, $key, 'a map with exception, caused_by or message');
        }

        return new Failure($exceptions, $causedBy, $message);
    }

    /**
     * Checks the tags inside a value, at any depth.
     *
     * @param list<string> $allowed The tags that can be in this place.
     * @param list<string> $previous The steps that a `!ref` can point to.
     */
    private function walkTags(mixed $value, string $path, string $key, array $allowed, array $previous): void
    {
        if ($value instanceof TaggedValue) {
            $this->checkTag($value, $path, $key, $allowed, $previous);

            return;
        }

        if (is_array($value)) {
            foreach ($value as $k => $item) {
                $this->walkTags($item, $path, $key . '.' . $k, $allowed, $previous);
            }
        }
    }

    /**
     * @param list<string> $allowed
     * @param list<string> $previous
     */
    private function checkTag(TaggedValue $tag, string $path, string $key, array $allowed, array $previous): void
    {
        $name = $tag->getTag();
        if (!in_array($name, $allowed, true)) {
            throw InvalidScenarioException::forTag($path, $key, $name);
        }

        $value = $tag->getValue();
        switch ($name) {
            case 'any':
                if ($value !== '' && $value !== null) {
                    throw InvalidScenarioException::forKey($path, $key, 'a tag !any without a value');
                }
                break;
            case 'instanceof':
                $this->nonEmptyString($value, $path, $key);
                break;
            case 'ref':
                $reference = $this->nonEmptyString($value, $path, $key);
                if (preg_match('/^[a-z][a-z0-9_]*/', $reference, $step) !== 1 || !in_array($step[0], $previous, true)) {
                    throw InvalidScenarioException::forReferenceToStep($path, $key, $reference);
                }
                break;
            case 'fixture':
                $this->checkFixtureTag($value, $path, $key, $allowed, $previous);
                break;
        }
    }

    /**
     * @param list<string> $allowed
     * @param list<string> $previous
     */
    private function checkFixtureTag(mixed $value, string $path, string $key, array $allowed, array $previous): void
    {
        if (is_string($value) && $value !== '') {
            return;
        }

        $data = $this->map($value, $path, $key, 'a fixture name or a map with name and args');
        $this->onlyKeys($data, ['name', 'args'], $path, $key);
        $this->nonEmptyString($data['name'] ?? null, $path, $key . '.name');
        $args = $this->map($data['args'] ?? [], $path, $key . '.args', 'a map');
        $this->walkTags($args, $path, $key . '.args', $allowed, $previous);
    }

    /**
     * @return array<string, mixed>
     */
    private function map(mixed $value, string $path, string $key, string $expected): array
    {
        if (!is_array($value) || ($value !== [] && array_is_list($value))) {
            throw InvalidScenarioException::forKey($path, $key, $expected);
        }

        $map = [];
        foreach ($value as $k => $item) {
            if (!is_string($k)) {
                throw InvalidScenarioException::forKey($path, $key, $expected);
            }
            $map[$k] = $item;
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $map
     * @param list<string> $allowed
     */
    private function onlyKeys(array $map, array $allowed, string $path, string $prefix): void
    {
        foreach (array_keys($map) as $key) {
            if (!in_array($key, $allowed, true)) {
                throw InvalidScenarioException::forUnknownKey($path, $prefix === '' ? $key : $prefix . '.' . $key);
            }
        }
    }

    private function nonEmptyString(mixed $value, string $path, string $key): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw InvalidScenarioException::forKey($path, $key, 'a non-empty string');
        }

        return $value;
    }
}
