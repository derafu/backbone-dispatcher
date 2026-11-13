<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\BackboneDispatcher\Testing;

use BackedEnum;
use Derafu\BackboneDispatcher\Contract\ObjectFactoryInterface;
use Derafu\BackboneDispatcher\Contract\SafeExplorerInterface;
use Derafu\Value\ValueCaster;
use Derafu\Value\ValueType;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

/**
 * The rules that every dispatcher has to follow, checked from the dispatcher
 * itself: no data and no scenario files, it reads what the explorer discovers.
 *
 * A dispatcher extends this class and implements `explorer()` and
 * `objectFactory()` to get the tests. Every rule is one test method, so a
 * failure points to one rule, and each one reports all the violations it finds
 * (one per line, `id: reason`), not only the first.
 *
 * The rules that are a decision of the project (the style of the names) are
 * hooks. A violation that cannot be fixed yet is listed in `exclusions()` with
 * its reason; an exclusion that no longer has a violation is itself a failure,
 * so the list cannot go stale.
 */
abstract class DispatcherConformanceTestCase extends TestCase
{
    /**
     * Types that need no deserializer, because they are not classes.
     */
    private const BUILTIN_TYPES = [
        'int', 'float', 'string', 'bool', 'array', 'mixed', 'null', 'iterable', 'object',
        'callable', 'false', 'true', 'void', 'never',
    ];

    /**
     * Types of a result that are not a class and are serializable as they are.
     */
    private const SERIALIZABLE_BUILTIN_TYPES = [
        'int', 'float', 'string', 'bool', 'array', 'null', 'iterable', 'false', 'true', 'void', 'never',
    ];

    /**
     * The tree of what the dispatcher exposes, by test class.
     *
     * @var array<class-string, list<array{kind: string, id: string, data: array<string, mixed>}>>
     */
    private static array $elements = [];

    /**
     * The explorer of the dispatcher: the real one.
     */
    abstract protected static function explorer(): SafeExplorerInterface;

    /**
     * The object factory of the dispatcher: the real one.
     */
    abstract protected static function objectFactory(): ObjectFactoryInterface;

    /**
     * Pattern of the names of packages, components, workers and operations.
     * By default, snake case.
     */
    protected function namePattern(): string
    {
        return '/^[a-z][a-z0-9]*(_[a-z0-9]+)*$/';
    }

    /**
     * Pattern of the names of the parameters, or null to not check them. By
     * default they are not checked: the style of the parameters is a decision
     * of each project.
     */
    protected function parameterNamePattern(): ?string
    {
        return null;
    }

    /**
     * The violations that are known and cannot be fixed yet.
     *
     * The key is the check (the name of its test without `test`, in snake case,
     * like `names_follow_the_convention`), the value is a map of the id that
     * has the violation to the reason why it is allowed.
     *
     * @return array<string, array<string, string>>
     */
    protected function exclusions(): array
    {
        return [];
    }

    public function testNamesFollowTheConvention(): void
    {
        $violations = [];
        foreach ($this->elements() as $element) {
            $name = (string) preg_replace('/^.*[.:]/', '', $element['id']);
            if (preg_match($this->namePattern(), $name) !== 1) {
                $violations[] = [$element['id'], sprintf('the %s name "%s" does not match %s.', $element['kind'], $name, $this->namePattern())];
            }

            $parameterPattern = $this->parameterNamePattern();
            if ($element['kind'] === 'operation' && $parameterPattern !== null) {
                foreach ($this->parameters($element) as $parameter) {
                    $parameterName = $this->text($parameter, 'name');
                    if (preg_match($parameterPattern, $parameterName) !== 1) {
                        $violations[] = [$element['id'], sprintf('the parameter "%s" does not match %s.', $parameterName, $parameterPattern)];
                    }
                }
            }
        }

        $this->assertNoViolations('names_follow_the_convention', $violations);
    }

    public function testOperationsAreDocumented(): void
    {
        $violations = [];
        foreach ($this->operations() as $operation) {
            foreach (['summary', 'description'] as $key) {
                $value = $operation['data'][$key] ?? null;
                if (!is_string($value) || trim($value) === '') {
                    $violations[] = [$operation['id'], sprintf('it has no %s.', $key)];
                }
            }
        }

        $this->assertNoViolations('operations_are_documented', $violations);
    }

    public function testParametersHaveExamples(): void
    {
        $violations = [];
        foreach ($this->operations() as $operation) {
            foreach ($this->parameters($operation) as $parameter) {
                if (!array_key_exists('example', $parameter)) {
                    $violations[] = [$operation['id'], sprintf('the parameter "%s" has no example.', $this->text($parameter, 'name'))];
                }
            }
        }

        $this->assertNoViolations('parameters_have_examples', $violations);
    }

    public function testExamplesAreEncodableAsJson(): void
    {
        $violations = [];
        foreach ($this->operations() as $operation) {
            foreach ($this->parameters($operation) as $parameter) {
                if (!array_key_exists('example', $parameter)) {
                    continue;
                }
                $invalid = $this->findNotEncodable($parameter['example'], $this->text($parameter, 'name'));
                if ($invalid !== null) {
                    $violations[] = [$operation['id'], sprintf('the example of the parameter "%s" has %s.', $this->text($parameter, 'name'), $invalid)];
                }
            }
        }

        $this->assertNoViolations('examples_are_encodable_as_json', $violations);
    }

    public function testObjectParametersHaveADeserializer(): void
    {
        $violations = [];
        foreach ($this->operations() as $operation) {
            foreach ($this->parameters($operation) as $parameter) {
                foreach ($this->classCandidates($this->text($parameter, 'type')) as $class) {
                    if (!enum_exists($class) && !static::objectFactory()->supports($class)) {
                        $violations[] = [$operation['id'], sprintf('the parameter "%s" is of type %s, which no deserializer builds.', $this->text($parameter, 'name'), $class)];
                    }
                }
            }
        }

        $this->assertNoViolations('object_parameters_have_a_deserializer', $violations);
    }

    public function testReturnTypesAreSerializable(): void
    {
        $violations = [];
        foreach ($this->operations() as $operation) {
            $returns = ValueType::map()->parse($operation['data']['returns'] ?? [], 'returns', self::class);
            $type = is_string($returns['type'] ?? null) ? $returns['type'] : 'mixed';
            foreach ($this->notSerializable($type) as $reason) {
                $violations[] = [$operation['id'], sprintf('the return type %s is not serializable: %s.', $type, $reason)];
            }
        }

        $this->assertNoViolations('return_types_are_serializable', $violations);
    }

    /**
     * Fails with the violations that are not excluded, and with the exclusions
     * that no longer have a violation.
     *
     * @param list<array{string, string}> $violations The id and the reason.
     */
    private function assertNoViolations(string $check, array $violations): void
    {
        $excluded = $this->exclusions()[$check] ?? [];

        $lines = [];
        $seen = [];
        foreach ($violations as [$id, $reason]) {
            $seen[$id] = true;
            if (!array_key_exists($id, $excluded)) {
                $lines[] = $id . ': ' . $reason;
            }
        }

        foreach ($excluded as $id => $why) {
            if (!isset($seen[$id])) {
                $lines[] = $id . ': the exclusion is stale, it has no violation (' . $why . ').';
            }
        }

        $this->assertSame([], $lines, "The rule \"$check\" has violations:\n" . implode("\n", $lines));
    }

    /**
     * The packages, components, workers and operations that the dispatcher
     * exposes, in the order of the tree.
     *
     * @return list<array{kind: string, id: string, data: array<string, mixed>}>
     */
    private function elements(): array
    {
        if (isset(self::$elements[static::class])) {
            return self::$elements[static::class];
        }

        $result = static::explorer()->tree(null);
        $this->assertTrue($result->isSuccess(), 'The explorer could not give the tree of the dispatcher: ' . (string) $result->getProblem());

        $elements = [];
        $root = ValueType::map()->parse($result->getValue(), 'tree', self::class);
        foreach ($this->children($root, 'packages') as $package) {
            $elements[] = $this->element('package', $package);
            foreach ($this->children($package, 'components') as $component) {
                $elements[] = $this->element('component', $component);
                foreach ($this->children($component, 'workers') as $worker) {
                    $elements[] = $this->element('worker', $worker);
                    foreach ($this->children($worker, 'operations') as $operation) {
                        $elements[] = $this->element('operation', $operation);
                    }
                }
            }
        }

        return self::$elements[static::class] = $elements;
    }

    /**
     * @return list<array{kind: string, id: string, data: array<string, mixed>}>
     */
    private function operations(): array
    {
        return array_values(array_filter(
            $this->elements(),
            static fn (array $element): bool => $element['kind'] === 'operation'
        ));
    }

    /**
     * @param array<string, mixed> $node
     * @return array{kind: string, id: string, data: array<string, mixed>}
     */
    private function element(string $kind, array $node): array
    {
        return ['kind' => $kind, 'id' => is_string($node['id'] ?? null) ? $node['id'] : '', 'data' => $node];
    }

    /**
     * @param array<string, mixed> $node
     * @return list<array<string, mixed>>
     */
    private function children(array $node, string $key): array
    {
        return ValueType::listOf(ValueType::map())->parse($node[$key] ?? [], $key, self::class);
    }

    /**
     * @param array{kind: string, id: string, data: array<string, mixed>} $operation
     * @return list<array<string, mixed>>
     */
    private function parameters(array $operation): array
    {
        return $this->children($operation['data'], 'parameters');
    }

    /**
     * @param array<string, mixed> $node
     */
    private function text(array $node, string $key): string
    {
        return ValueCaster::requireString($node[$key] ?? null);
    }

    /**
     * Describes what, inside an example, a client cannot send as JSON: any
     * object that is not a case of a backed enum.
     */
    private function findNotEncodable(mixed $value, string $path): ?string
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $found = $this->findNotEncodable($item, $path . '.' . $key);

                if ($found !== null) {
                    return $found;
                }
            }

            return null;
        }

        if (is_object($value) && !$value instanceof BackedEnum) {
            return sprintf('an object of %s at %s', $value::class, $path);
        }

        return is_resource($value) ? sprintf('a resource at %s', $path) : null;
    }

    /**
     * The classes that a parameter type needs a deserializer for: its
     * candidates that are not built-in types.
     *
     * @return list<string>
     */
    private function classCandidates(string $type): array
    {
        $classes = [];
        foreach (explode('|', $type) as $candidate) {
            $candidate = preg_replace('/\[\]$/', '', ltrim(trim($candidate), '?')) ?? '';
            if ($candidate !== '' && !in_array($candidate, self::BUILTIN_TYPES, true)) {
                $classes[] = $candidate;
            }
        }

        return $classes;
    }

    /**
     * The reasons why a return type is not serializable: every candidate of it
     * has to be. The type of the return is the reflected one, so it is a
     * built-in type, a class or an enum, or a union or an intersection of them.
     *
     * @return list<string>
     */
    private function notSerializable(string $type): array
    {
        $reasons = [];
        foreach (explode('|', $type) as $candidate) {
            $candidate = ltrim(trim($candidate), '?');

            if ($candidate === '' || in_array($candidate, self::SERIALIZABLE_BUILTIN_TYPES, true)) {
                continue;
            }

            if ($candidate === 'mixed' || $candidate === 'object') {
                $reasons[] = sprintf('%s cannot be known to be serializable', $candidate);
                continue;
            }

            $reason = $this->notSerializableClass($candidate);
            if ($reason !== null) {
                $reasons[] = $reason;
            }
        }

        return $reasons;
    }

    /**
     * Why a class, an enum or an intersection of them is not serializable, or
     * null when it is. An intersection is serializable when any of its parts
     * is.
     */
    private function notSerializableClass(string $candidate): ?string
    {
        foreach (explode('&', $candidate) as $part) {
            if (enum_exists($part) || is_a($part, JsonSerializable::class, true) || method_exists($part, 'toArray')) {
                return null;
            }
        }

        return sprintf('%s is not JsonSerializable and has no toArray()', $candidate);
    }
}
