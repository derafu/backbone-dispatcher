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
 * `derafu/config`), with a few real jobs used to exercise the Resolver,
 * Caster, ObjectFactory, and Dispatcher against real, reflectable methods.
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
     * A job with a required and an optional scalar parameter.
     */
    public function sum(int $a, int $b = 10): int
    {
        return $a + $b;
    }

    /**
     * A job with a required object parameter, hydrated from an array by the
     * ObjectFactory.
     */
    public function describeBag(ExampleBag $bag): array
    {
        return [
            'name' => $bag->getName(),
            'doubled' => $bag->getAmount() * 2,
        ];
    }

    /**
     * A job that returns a domain object (not an array), so the Dispatcher
     * must flatten it via the Serializer before returning it.
     */
    public function makeGreeting(string $name): ExampleGreeting
    {
        return new ExampleGreeting(
            message: "Hello, {$name}!",
            reply: new ExampleGreeting('Hi there!'),
        );
    }

    /**
     * A job that always fails, used to verify that the Dispatcher lets a
     * job's own exceptions propagate unaltered.
     */
    public function fail(): never
    {
        throw new RuntimeException('Something went wrong while running the job.');
    }
}
