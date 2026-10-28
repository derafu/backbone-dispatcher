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

use Derafu\BackboneDispatcher\Abstract\AbstractDeserializer;
use Derafu\BackboneDispatcher\Contract\ObjectFactoryInterface;
use Derafu\BackboneDispatcher\Exception\UnsupportedDataTypeException;

/**
 * Builds nested greetings through the same factory to which it contributes.
 */
final class NestedGreetingDeserializer extends AbstractDeserializer
{
    public function __construct(private readonly ObjectFactoryInterface $factory)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function deserialize(array|string $data, string $class): object
    {
        $data = $this->assertArray($data);
        $message = $data['message'] ?? null;
        $reply = $data['reply'] ?? null;
        if (!is_string($message) || ($reply !== null && !is_array($reply))) {
            throw UnsupportedDataTypeException::forDeserializer(self::class, 'a message with an optional nested reply');
        }
        $reply = $this->factory->create($reply === null ? null : [
            'message' => $reply['message'] ?? null,
            'reply' => $reply['reply'] ?? null,
        ], ExampleGreeting::class);
        if ($reply !== null && !$reply instanceof ExampleGreeting) {
            throw UnsupportedDataTypeException::forDeserializer(self::class, 'ExampleGreeting');
        }

        return new ExampleGreeting($message, $reply);
    }
}
