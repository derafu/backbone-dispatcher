<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsBackboneDispatcher;

use Derafu\BackboneDispatcher\Service\Reflection\Inspector;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleWorker;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleWorkerSubclass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Covers `isOperation()`, `hasOperationAttribute()` and
 * `getOperationParameters()` — the narrow, PHPDoc-free methods the dispatch
 * path relies on. The rest
 * of `Inspector` (documentation reading) is exercised indirectly through
 * the dispatcher integration tests for now.
 */
#[CoversClass(Inspector::class)]
class InspectorTest extends TestCase
{
    private Inspector $inspector;

    private ExampleWorker $worker;

    protected function setUp(): void
    {
        $this->inspector = new Inspector();
        $this->worker = new ExampleWorker();
    }

    public function testIsOperationIsTrueForAPublicMethodDeclaredOnTheClass(): void
    {
        $this->assertTrue($this->inspector->isOperation($this->worker, 'sum'));
        $this->assertTrue($this->inspector->isOperation($this->worker, 'describeBag'));
    }

    public function testIsOperationIsFalseForANonexistentMethod(): void
    {
        $this->assertFalse($this->inspector->isOperation($this->worker, 'doesNotExist'));
    }

    public function testIsOperationIsFalseForAnUnderscorePrefixedName(): void
    {
        $this->assertFalse($this->inspector->isOperation($this->worker, '_hidden'));
    }

    public function testHasOperationAttributeIsTrueOnlyForATaggedOperation(): void
    {
        $this->assertTrue($this->inspector->hasOperationAttribute($this->worker, 'sum'));
        $this->assertFalse($this->inspector->hasOperationAttribute($this->worker, 'describeBag'));
    }

    public function testHasOperationAttributeIsFalseForANonexistentMethod(): void
    {
        $this->assertFalse($this->inspector->hasOperationAttribute($this->worker, 'doesNotExist'));
    }

    /**
     * `sum()` is declared on `ExampleWorker`, not on `ExampleWorkerSubclass`
     * — it must still count as an operation of the subclass: whether a
     * method is "an operation" is a fact about the method (public, tagged),
     * never about which class in the hierarchy happens to declare it.
     */
    public function testIsOperationIsTrueForAnInheritedMethodDeclaredOnAParentClass(): void
    {
        $subclass = new ExampleWorkerSubclass();

        $this->assertTrue($this->inspector->isOperation($subclass, 'sum'));
    }

    public function testHasOperationAttributeIsTrueForAnInheritedTaggedMethod(): void
    {
        $subclass = new ExampleWorkerSubclass();

        $this->assertTrue($this->inspector->hasOperationAttribute($subclass, 'sum'));
    }

    public function testGetPublicMethodsIncludesBothInheritedAndOwnTaggedMethods(): void
    {
        $subclass = new ExampleWorkerSubclass();

        $methods = $this->inspector->getPublicMethods($subclass);

        $this->assertArrayHasKey('sum', $methods);
        $this->assertArrayHasKey('multiply', $methods);
    }

    /**
     * `getTaggedOperations()`/`getPublicMethods()`'s `$filters['operation']`
     * were `Inspector`'s own, hardcoded reimplementation of exactly the rule
     * `TaggedOperationPolicy` already embodies (whether a method carries
     * `#[Operation]`) — a second, policy-independent way to decide "what
     * counts as an operation", reachable straight off `Inspector` without
     * ever going through an `OperationPolicyInterface`. That contradicts the
     * same principle the inheritance fix above enforces: only a Policy may
     * draw that line. A consumer who wants "the tagged operations of a
     * worker" must get there the same way `Explorer` does — enumerate with
     * `getPublicMethods($service)` (no filtering) and ask a Policy
     * (`TaggedOperationPolicy`, via `hasOperationAttribute()`) about each
     * candidate — never a shortcut baked into `Inspector` itself.
     */
    public function testGetPublicMethodsNeverFiltersByTheOperationAttributeItself(): void
    {
        $this->assertFalse(
            method_exists(Inspector::class, 'getTaggedOperations'),
        );

        $reflection = new ReflectionMethod(Inspector::class, 'getPublicMethods');

        $this->assertCount(
            1,
            $reflection->getParameters(),
            'getPublicMethods() must take only $service — no $filters bag through '
                . 'which Inspector could decide, on its own, what counts as an operation.'
        );
    }

    public function testGetOperationParametersResolvesRequiredAndOptionalParameters(): void
    {
        $parameters = $this->inspector->getOperationParameters($this->worker, 'sum');

        $this->assertSame('a', $parameters[0]['name']);
        $this->assertSame('int', $parameters[0]['type']);
        $this->assertTrue($parameters[0]['required']);

        $this->assertSame('b', $parameters[1]['name']);
        $this->assertFalse($parameters[1]['required']);
        $this->assertSame(10, $parameters[1]['default']);
    }

    public function testGetOperationParametersNeverParsesPhpDoc(): void
    {
        // sum() has no @param description in its docblock's own text, but
        // this asserts the contract, not an implementation detail: the
        // description is always null here, regardless of what the docblock
        // says, because getOperationParameters() never looks at it.
        $parameters = $this->inspector->getOperationParameters($this->worker, 'sum');

        $this->assertNull($parameters[0]['description']);
    }

    public function testOperationAttributeNameAndDescriptionOverridePhpDoc(): void
    {
        $methods = $this->inspector->getPublicMethods($this->worker);

        $this->assertSame('Sum', $methods['sum']['summary']);
        $this->assertSame(
            'Adds two integers together.',
            $methods['sum']['description']
        );
    }

    public function testOperationAttributeParameterOverridesApplyOnTopOfReflection(): void
    {
        $methods = $this->inspector->getPublicMethods($this->worker);
        $parameters = $methods['sum']['parameters'];

        // 'a' only overrides 'example' — 'type'/'required' stay reflected.
        $this->assertSame('int', $parameters[0]['type']);
        $this->assertTrue($parameters[0]['required']);
        $this->assertSame(5, $parameters[0]['example']);

        // 'b' only overrides 'description' — no 'example' key is added.
        $this->assertSame(
            'The addend, defaults to 10.',
            $parameters[1]['description']
        );
        $this->assertArrayNotHasKey('example', $parameters[1]);
    }

    public function testOperationWithoutOverridesKeepsReflectedNameAndDescription(): void
    {
        $methods = $this->inspector->getPublicMethods($this->worker);

        // describeBag() has no #[Operation] attribute at all, so nothing
        // here is overridden — summary/description come straight from
        // PHPDoc, parameters have no 'example' key.
        $this->assertNull($methods['describeBag']['operation']);
        $this->assertArrayNotHasKey('example', $methods['describeBag']['parameters'][0]);
    }
}
