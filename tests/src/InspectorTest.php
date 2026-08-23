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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

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
