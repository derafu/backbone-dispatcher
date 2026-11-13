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
use Derafu\BackboneDispatcher\Testing\Exception\FixtureUnavailableException;
use Derafu\BackboneDispatcher\Testing\Exception\InvalidScenarioException;
use Derafu\BackboneDispatcher\Testing\Fixture\FixtureRegistry;
use Derafu\Value\ValueCaster;
use Derafu\Value\ValueType;
use Exception;
use JmesPath\Env;
use Symfony\Component\Yaml\Tag\TaggedValue;

/**
 * Replaces the `!fixture` and `!ref` tags of a value with what they point to.
 *
 * `!any` and `!instanceof` are not replaced: they are assertions, and
 * `ExpectationChecker` evaluates them.
 */
final class TagResolver
{
    /**
     * @param FixtureRegistry $fixtures
     * @throws MissingOptionalDependencyException When `mtdowling/jmespath.php`
     * is not installed.
     */
    public function __construct(private readonly FixtureRegistry $fixtures)
    {
        if (!class_exists(Env::class)) {
            throw MissingOptionalDependencyException::forFeature(self::class, 'mtdowling/jmespath.php');
        }
    }

    /**
     * Resolves the tags of a value, at any depth.
     *
     * @param mixed $value The value, with `TaggedValue` objects where the file
     * has tags.
     * @param array<string, array{value: mixed, problem: array{type: string, title: string, detail: string}|null}> $references
     * The results of the previous steps, by name of step.
     * @return mixed
     * @throws InvalidScenarioException When a fixture is not registered, or a
     * reference points to a value that the results do not have or that is
     * null (a JMESPath expression cannot tell the two apart), or an expression
     * is not valid.
     * @throws FixtureUnavailableException When a fixture needs a requirement
     * that is not available.
     */
    public function resolve(mixed $value, array $references): mixed
    {
        if ($value instanceof TaggedValue) {
            return match ($value->getTag()) {
                'fixture' => $this->fixture($value->getValue(), $references),
                'ref' => $this->reference($value->getValue(), $references),
                default => $value,
            };
        }

        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->resolve($item, $references), $value);
        }

        return $value;
    }

    /**
     * @param array<string, array{value: mixed, problem: array{type: string, title: string, detail: string}|null}> $references
     */
    private function fixture(mixed $value, array $references): mixed
    {
        if (is_string($value)) {
            return $this->fixtures->resolve($value);
        }

        $tag = ValueType::map()->parse($value, 'fixture', self::class);
        $arguments = ValueType::map()->parse($this->resolve($tag['args'] ?? [], $references), 'fixture.args', self::class);

        return $this->fixtures->resolve(ValueCaster::requireString($tag['name'] ?? null), $arguments);
    }

    /**
     * @param array<string, array{value: mixed, problem: array{type: string, title: string, detail: string}|null}> $references
     */
    private function reference(mixed $path, array $references): mixed
    {
        $path = ValueCaster::requireString($path);

        try {
            $value = Env::search($path, $references);
        } catch (Exception $e) {
            throw InvalidScenarioException::forExpression($path, $e);
        }

        if ($value === null) {
            throw InvalidScenarioException::forMissingReferenceValue($path);
        }

        return $value;
    }
}
