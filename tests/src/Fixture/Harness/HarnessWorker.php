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
use Derafu\TestsBackboneDispatcher\Fixture\ExampleBag;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleIntBackedEnum;
use JsonSerializable;
use RuntimeException;

/**
 * A worker whose operations follow every rule of
 * `DispatcherConformanceTestCase`.
 */
class HarnessWorker implements WorkerInterface
{
    use JobsAwareTrait;
    use HandlersAwareTrait;
    use OptionsAwareTrait;

    public function getId(): int|string
    {
        return 'harness_worker';
    }

    public function getName(): string
    {
        return 'Harness Worker';
    }

    public function getDescription(): ?string
    {
        return 'A worker that follows every rule of the conformance suite.';
    }

    public function __toString(): string
    {
        return $this->getName();
    }

    /**
     * Greets a name.
     *
     * Answers with a map that has a list inside.
     *
     * @return array{message: string, tags: list<string>}
     */
    #[Operation(
        name: 'say_hello',
        parameters: [
            'name' => ['example' => 'Ana'],
        ],
    )]
    public function sayHello(string $name): array
    {
        return ['message' => "Hello, {$name}!", 'tags' => ['a', 'b']];
    }

    /**
     * Describes a bag.
     *
     * Receives an object that the object factory builds from an array.
     *
     * @return array{name: string, doubled: int}
     */
    #[Operation(
        name: 'describe_bag',
        parameters: [
            'bag' => ['example' => ['name' => 'folios', 'amount' => 3]],
        ],
    )]
    public function describeBag(ExampleBag $bag): array
    {
        return ['name' => $bag->getName(), 'doubled' => $bag->getAmount() * 2];
    }

    /**
     * Gives a document back.
     *
     * It receives and returns the same type, so what it answers can be sent
     * again.
     */
    #[Operation(
        name: 'echo_document',
        parameters: [
            'document' => ['example' => 'doc:abc'],
        ],
    )]
    public function echoDocument(HarnessDocument $document): HarnessDocument
    {
        return $document;
    }

    /**
     * Names the case of an enum.
     *
     * The example of the parameter is a case of a backed enum, which a client
     * sends as its value.
     *
     * @return string
     */
    #[Operation(
        name: 'enum_name',
        parameters: [
            'case' => ['example' => ExampleIntBackedEnum::Low],
        ],
    )]
    public function enumName(ExampleIntBackedEnum $case): string
    {
        return $case->name;
    }

    /**
     * Always fails.
     *
     * It throws, to have an operation that ends in a problem.
     */
    #[Operation(name: 'fail')]
    public function fail(): never
    {
        throw new RuntimeException('Something went wrong in the harness.');
    }

    /**
     * Gives an object that is serializable by one of the parts of its type.
     *
     * The return type is an intersection, and one of its parts is
     * `JsonSerializable`.
     */
    #[Operation(name: 'serializable_intersection')]
    public function serializableIntersection(): JsonSerializable&Countable
    {
        return new class () implements JsonSerializable, Countable {
            public function count(): int
            {
                return 0;
            }

            /**
             * @return array{}
             */
            public function jsonSerialize(): array
            {
                return [];
            }
        };
    }
}
