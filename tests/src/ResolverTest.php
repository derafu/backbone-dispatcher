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

use Derafu\BackboneDispatcher\Exception\InvalidParameterTypeException;
use Derafu\BackboneDispatcher\Exception\MissingParameterException;
use Derafu\BackboneDispatcher\Service\Caster;
use Derafu\BackboneDispatcher\Service\FromArrayDeserializer;
use Derafu\BackboneDispatcher\Service\Inspector;
use Derafu\BackboneDispatcher\Service\ObjectFactoryRegistry;
use Derafu\BackboneDispatcher\Service\Resolver;
use Derafu\BackboneDispatcher\Service\Validator;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleBag;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleBagDeserializer;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleWorker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Resolver::class)]
#[UsesClass(Inspector::class)]
#[UsesClass(Caster::class)]
#[UsesClass(ObjectFactoryRegistry::class)]
#[UsesClass(FromArrayDeserializer::class)]
#[UsesClass(Validator::class)]
#[UsesClass(MissingParameterException::class)]
#[UsesClass(InvalidParameterTypeException::class)]
class ResolverTest extends TestCase
{
    private Resolver $resolver;

    private ExampleWorker $worker;

    protected function setUp(): void
    {
        $this->resolver = new Resolver(
            new Inspector(),
            new Caster(new ObjectFactoryRegistry(fallback: new FromArrayDeserializer())),
            new Validator()
        );
        $this->worker = new ExampleWorker();
    }

    public function testResolvesRequiredAndOptionalScalarParametersInOrder(): void
    {
        // Values must already be of their native PHP type (as json_decode()
        // produces for a JSON number): Validator::validate() runs before
        // Caster::cast(), and cast() is only reachable for the object
        // deserialization case through this call path, not for scalar coercion
        // (e.g. a numeric string is never coerced into an int).
        $args = $this->resolver->resolve($this->worker, 'sum', ['a' => 5]);

        $this->assertSame(['a' => 5, 'b' => 10], $args);
    }

    public function testExplicitValueOverridesTheDefault(): void
    {
        $args = $this->resolver->resolve($this->worker, 'sum', ['a' => 5, 'b' => 2]);

        $this->assertSame(['a' => 5, 'b' => 2], $args);
    }

    public function testThrowsWhenARequiredParameterIsMissing(): void
    {
        $this->expectException(MissingParameterException::class);
        $this->expectExceptionMessage('The parameter a of type int is missing from the request data.');

        $this->resolver->resolve($this->worker, 'sum', []);
    }

    public function testThrowsWhenAParameterHasAnInvalidType(): void
    {
        $this->expectException(InvalidParameterTypeException::class);
        $this->expectExceptionMessage('The parameter a must be of type int.');

        $this->resolver->resolve($this->worker, 'sum', ['a' => ['not', 'an', 'int']]);
    }

    public function testResolvesAndDeserializesAnObjectParameter(): void
    {
        $args = $this->resolver->resolve($this->worker, 'describeBag', [
            'bag' => ['name' => 'folios', 'amount' => 4],
        ]);

        $this->assertInstanceOf(ExampleBag::class, $args['bag']);
        $this->assertSame('folios', $args['bag']->getName());
        $this->assertSame(4, $args['bag']->getAmount());
    }

    public function testResolvesAndDeserializesAnObjectParameterUsingARegisteredDeserializer(): void
    {
        // Unlike setUp()'s $this->resolver (fallback-only), this one has an
        // explicit deserializer registered for ExampleBag — the path real
        // libraries (e.g. Certificate/Caf in libredte-lib-pro-bridge) will
        // actually use, not the fromArray() convention.
        $resolver = new Resolver(
            new Inspector(),
            new Caster(new ObjectFactoryRegistry(
                deserializers: [ExampleBag::class => new ExampleBagDeserializer()],
                fallback: new FromArrayDeserializer(),
            )),
            new Validator()
        );

        $args = $resolver->resolve($this->worker, 'describeBag', [
            'bag' => ['name' => 'folios', 'amount' => 4],
        ]);

        $this->assertInstanceOf(ExampleBag::class, $args['bag']);
        // The registered deserializer marks the name; fromArray() would not.
        $this->assertSame('folios (via registry)', $args['bag']->getName());
    }
}
