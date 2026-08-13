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
use Derafu\BackboneDispatcher\Service\Inspector;
use Derafu\BackboneDispatcher\Service\ObjectFactory;
use Derafu\BackboneDispatcher\Service\Resolver;
use Derafu\BackboneDispatcher\Service\Validator;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleBag;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleWorker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Resolver::class)]
#[UsesClass(Inspector::class)]
#[UsesClass(Caster::class)]
#[UsesClass(ObjectFactory::class)]
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
            new Caster(new ObjectFactory()),
            new Validator()
        );
        $this->worker = new ExampleWorker();
    }

    public function testResolvesRequiredAndOptionalScalarParametersInOrder(): void
    {
        // Values must already be of their native PHP type (as json_decode()
        // produces for a JSON number): Validator::validate() runs before
        // Caster::cast(), and cast() is only reachable for the object
        // hydration case through this call path, not for scalar coercion
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

    public function testResolvesAndHydratesAnObjectParameter(): void
    {
        $args = $this->resolver->resolve($this->worker, 'describeBag', [
            'bag' => ['name' => 'folios', 'amount' => 4],
        ]);

        $this->assertInstanceOf(ExampleBag::class, $args['bag']);
        $this->assertSame('folios', $args['bag']->getName());
        $this->assertSame(4, $args['bag']->getAmount());
    }
}
