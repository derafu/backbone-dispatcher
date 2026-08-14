<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsBackboneDispatcher\Fixture;

use Derafu\Backbone\Contract\WorkerInterface;
use Derafu\Backbone\Trait\HandlersAwareTrait;
use Derafu\Backbone\Trait\JobsAwareTrait;
use Derafu\Config\Trait\OptionsAwareTrait;
use RuntimeException;

/**
 * A real worker (uses the actual production traits of `derafu/backbone` and
 * `derafu/config`), with a few real operations used to exercise the
 * Resolver, Caster, ObjectFactory, and Dispatcher against real, reflectable
 * methods. `JobsAwareTrait` is used here only because `WorkerInterface`
 * requires it; none of the operations below go through it or relate to a
 * real `JobInterface` instance.
 */
class ExampleWorker implements WorkerInterface
{
    use JobsAwareTrait;
    use HandlersAwareTrait;
    use OptionsAwareTrait;

    public function getId(): int|string
    {
        return 'example_worker';
    }

    public function getName(): string
    {
        return 'Example Worker';
    }

    public function __toString(): string
    {
        return $this->getName();
    }

    /**
     * An operation with a required and an optional scalar parameter.
     */
    public function sum(int $a, int $b = 10): int
    {
        return $a + $b;
    }

    /**
     * An operation with a required object parameter, deserialized from an
     * array by the ObjectFactoryRegistry.
     */
    public function describeBag(ExampleBag $bag): array
    {
        return [
            'name' => $bag->getName(),
            'doubled' => $bag->getAmount() * 2,
        ];
    }

    /**
     * An operation that returns a domain object (not an array). Used to
     * verify that `DirectDispatcher`/`TypedDispatcher` return it unaltered,
     * and that `SafeDispatcher` flattens it via the Serializer.
     */
    public function makeGreeting(string $name): ExampleGreeting
    {
        return new ExampleGreeting(
            message: "Hello, {$name}!",
            reply: new ExampleGreeting('Hi there!'),
        );
    }

    /**
     * An operation that always fails, used to verify that the Dispatcher
     * lets an operation's own exceptions propagate unaltered.
     */
    public function fail(): never
    {
        throw new RuntimeException('Something went wrong while running the operation.');
    }
}
