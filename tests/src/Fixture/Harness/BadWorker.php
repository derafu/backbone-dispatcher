<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsBackboneDispatcher\Fixture\Harness;

use Countable;
use Derafu\Backbone\Attribute\Operation;
use Derafu\Backbone\Contract\WorkerInterface;
use Derafu\Backbone\Trait\HandlersAwareTrait;
use Derafu\Backbone\Trait\JobsAwareTrait;
use Derafu\Config\Trait\OptionsAwareTrait;
use LogicException;
use stdClass;
use Stringable;

/**
 * A worker that breaks, on purpose, every rule of
 * `DispatcherConformanceTestCase`.
 */
class BadWorker implements WorkerInterface
{
    use JobsAwareTrait;
    use HandlersAwareTrait;
    use OptionsAwareTrait;

    public function getId(): int|string
    {
        return 'bad_worker';
    }

    public function getName(): string
    {
        return 'Bad Worker';
    }

    public function getDescription(): ?string
    {
        return 'A worker that breaks the rules of the conformance suite.';
    }

    public function __toString(): string
    {
        return $this->getName();
    }

    /**
     * Has a name in camel case, parameters without examples, a parameter that
     * no deserializer builds and a result that is not serializable.
     */
    #[Operation(name: 'plainOperation')]
    public function plainOperation(HarnessDocument $document, string $someText): stdClass
    {
        return new stdClass();
    }

    /**
     * It has an example that a client cannot send as JSON.
     */
    #[Operation(
        name: 'object_example',
        summary: 'Object example',
        parameters: [
            'payload' => ['example' => ['items' => [1, new stdClass()]]],
        ],
    )]
    public function objectExample(string $payload): mixed
    {
        return null;
    }

    /**
     * Returns an intersection of types, none of them serializable.
     */
    #[Operation(name: 'intersection', summary: 'Intersection')]
    public function intersection(): Stringable&Countable
    {
        throw new LogicException('It cannot be called.');
    }
}
