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

use Derafu\BackboneDispatcher\Service\Reflection\Inspector;

/**
 * Runs the real inspector and records how often each entry point is called.
 */
class CountingInspector extends Inspector
{
    /** @var array<string, int> */
    public array $calls = [
        'getClassDoc' => 0,
        'getPublicMethods' => 0,
        'isOperation' => 0,
        'hasOperationAttribute' => 0,
        'getOperationParameters' => 0,
    ];

    /**
     * {@inheritDoc}
     */
    public function getClassDoc(object $service): array
    {
        ++$this->calls['getClassDoc'];

        return parent::getClassDoc($service);
    }

    /**
     * {@inheritDoc}
     */
    public function getPublicMethods(object $service): array
    {
        ++$this->calls['getPublicMethods'];

        return parent::getPublicMethods($service);
    }

    /**
     * {@inheritDoc}
     */
    public function isOperation(object $service, string $method): bool
    {
        ++$this->calls['isOperation'];

        return parent::isOperation($service, $method);
    }

    /**
     * {@inheritDoc}
     */
    public function hasOperationAttribute(object $service, string $method): bool
    {
        ++$this->calls['hasOperationAttribute'];

        return parent::hasOperationAttribute($service, $method);
    }

    /**
     * {@inheritDoc}
     */
    public function getOperationParameters(object $service, string $method): array
    {
        ++$this->calls['getOperationParameters'];

        return parent::getOperationParameters($service, $method);
    }
}
