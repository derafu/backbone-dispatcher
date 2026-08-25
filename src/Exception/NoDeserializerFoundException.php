<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\BackboneDispatcher\Exception;

use Throwable;

/**
 * Exception for a type with no registered `DeserializerInterface` able to
 * handle it, and no fallback able to build it either.
 */
class NoDeserializerFoundException extends ObjectFactoryException
{
    /**
     * Returns a new exception for a type with no deserializer able to
     * handle it.
     *
     * `$rejections` is what every candidate of a union type failed with
     * along the way (see `ObjectFactoryRegistry::create()`) — without it,
     * this exception would only ever say "nothing worked", discarding
     * exactly why each candidate didn't: e.g. one needed an array and got
     * a string, another needed a specific key that was missing. Both go
     * into the final message, and the last rejection becomes this
     * exception's `getPrevious()`, so nothing is silently swallowed even
     * when every candidate is rejected.
     *
     * @param string $class The requested type (may be a union type string).
     * @param array<string, Throwable> $rejections Keyed by the candidate
     * that was tried and rejected, in the order they were tried.
     * @return self
     */
    public static function forClass(string $class, array $rejections = []): self
    {
        if ($rejections === []) {
            return new self([
                'No deserializer was found able to build an instance of {class}.',
                'class' => $class,
            ]);
        }

        $details = [];
        foreach ($rejections as $candidate => $reason) {
            $details[] = sprintf('%s (%s)', $candidate, $reason->getMessage());
        }

        return new self(
            [
                'No deserializer was found able to build an instance of {class}. Every candidate was tried and rejected: {details}.',
                'class' => $class,
                'details' => implode('; ', $details),
            ],
            0,
            end($rejections),
        );
    }
}
