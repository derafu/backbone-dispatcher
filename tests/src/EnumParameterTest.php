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

use Derafu\BackboneDispatcher\Exception\InvalidEnumValueException;
use Derafu\BackboneDispatcher\Service\Deserialization\FromArrayDeserializer;
use Derafu\BackboneDispatcher\Service\Deserialization\ObjectFactoryRegistry;
use Derafu\BackboneDispatcher\Service\Reflection\Inspector;
use Derafu\BackboneDispatcher\Service\Resolution\Caster;
use Derafu\BackboneDispatcher\Service\Resolution\Resolver;
use Derafu\BackboneDispatcher\Service\Resolution\Validator;
use Derafu\BackboneDispatcher\Translation\BackboneDispatcherTranslationResourceProvider;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleBackedEnum;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleEnumWorker;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleIntBackedEnum;
use Derafu\Translation\TranslatorFactory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Integration test: the parameters of an operation that are backed enums are
 * resolved from their backing values through the real Resolver.
 */
#[CoversNothing]
class EnumParameterTest extends TestCase
{
    private Resolver $resolver;

    private ExampleEnumWorker $worker;

    protected function setUp(): void
    {
        $this->worker = new ExampleEnumWorker();
        $this->resolver = new Resolver(
            new Inspector(),
            new Caster(new ObjectFactoryRegistry(fallback: new FromArrayDeserializer())),
            new Validator()
        );
    }

    public function testBackingValuesAreResolvedToTheirCases(): void
    {
        $args = $this->resolver->resolve($this->worker, 'describe', [
            'status' => 'active',
            'level' => 2,
        ]);

        $status = $args['status'];
        $level = $args['level'];
        $this->assertSame(ExampleBackedEnum::Active, $status);
        $this->assertSame(ExampleIntBackedEnum::High, $level);
        $this->assertNull($args['previous']);
        $this->assertInstanceOf(ExampleBackedEnum::class, $status);
        $this->assertInstanceOf(ExampleIntBackedEnum::class, $level);
        $this->assertSame('active:2:-', $this->worker->describe($status, $level));
    }

    public function testAnOptionalEnumParameterIsResolvedWhenItIsGiven(): void
    {
        $args = $this->resolver->resolve($this->worker, 'describe', [
            'status' => 'active',
            'level' => 1,
            'previous' => 'inactive',
        ]);

        $this->assertSame(ExampleBackedEnum::Inactive, $args['previous']);
    }

    public function testAnInvalidValueIsRejectedListingTheAllowedValues(): void
    {
        try {
            $this->resolver->resolve($this->worker, 'describe', ['status' => 'deleted', 'level' => 1]);
            $this->fail('The value must be rejected.');
        } catch (InvalidEnumValueException $exception) {
            $this->assertSame(
                'The value "deleted" is not valid for the enum ' . ExampleBackedEnum::class . '. The allowed values are: "active", "inactive".',
                $exception->getMessage()
            );
        }
    }

    public function testAnIntegerAsAStringIsNotConvertedForAnIntBackedEnum(): void
    {
        $this->expectException(InvalidEnumValueException::class);
        $this->expectExceptionMessage('The value "2" is not valid for the enum ' . ExampleIntBackedEnum::class . '. The allowed values are: 1, 2.');

        $this->resolver->resolve($this->worker, 'describe', ['status' => 'active', 'level' => '2']);
    }

    public function testTheMessageOfAnInvalidValueIsTranslated(): void
    {
        try {
            $this->resolver->resolve($this->worker, 'describe', ['status' => 'active', 'level' => 3]);
            $this->fail('The value must be rejected.');
        } catch (InvalidEnumValueException $exception) {
            $translator = TranslatorFactory::create('es', resourceProviders: [new BackboneDispatcherTranslationResourceProvider()]);
            $this->assertSame(
                'El valor 3 no es válido para el enum ' . ExampleIntBackedEnum::class . '. Los valores permitidos son: 1, 2.',
                $exception->trans($translator)
            );
        }
    }
}
