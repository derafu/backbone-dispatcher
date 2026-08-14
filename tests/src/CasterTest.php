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

use Derafu\BackboneDispatcher\Service\Caster;
use Derafu\BackboneDispatcher\Service\FromArrayDeserializer;
use Derafu\BackboneDispatcher\Service\ObjectFactoryRegistry;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleBag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Caster::class)]
#[UsesClass(ObjectFactoryRegistry::class)]
#[UsesClass(FromArrayDeserializer::class)]
class CasterTest extends TestCase
{
    private Caster $caster;

    protected function setUp(): void
    {
        $this->caster = new Caster(
            new ObjectFactoryRegistry(fallback: new FromArrayDeserializer())
        );
    }

    public function testNullIsAlwaysReturnedAsNull(): void
    {
        $this->assertNull($this->caster->cast(null, 'string', 'int'));
    }

    public function testSameFromAndToTypeReturnsValueUnchanged(): void
    {
        $this->assertSame('5', $this->caster->cast('5', 'string', 'string'));
    }

    #[TestWith(['5', 'int', 5])]
    #[TestWith(['5.5', 'float', 5.5])]
    #[TestWith(['1', 'bool', true])]
    #[TestWith(['', 'bool', false])]
    public function testCastsStringToScalarTypes(string $value, string $to, mixed $expected): void
    {
        $this->assertSame($expected, $this->caster->cast($value, 'string', $to));
    }

    public function testCastingObjectToArrayReturnsTheArrayUnchanged(): void
    {
        $data = ['name' => 'folios', 'amount' => 3];

        $this->assertSame($data, $this->caster->cast($data, 'object', 'array'));
    }

    public function testCastingObjectToAClassCreatesItThroughTheObjectFactoryRegistry(): void
    {
        $bag = $this->caster->cast(
            ['name' => 'folios', 'amount' => 3],
            'object',
            ExampleBag::class
        );

        $this->assertInstanceOf(ExampleBag::class, $bag);
        $this->assertSame('folios', $bag->getName());
        $this->assertSame(3, $bag->getAmount());
    }

    #[TestWith(['string', 'string'])]
    #[TestWith(['float', 'number'])]
    #[TestWith(['int', 'integer'])]
    #[TestWith(['bool', 'boolean'])]
    #[TestWith(['array', 'array'])]
    #[TestWith([ExampleBag::class, 'object'])]
    public function testResolvesPhpTypeToGenericTypeName(string $type, string $expected): void
    {
        $this->assertSame($expected, $this->caster->resolveType($type));
    }
}
