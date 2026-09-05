<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\BackboneDispatcher\Service\Resolution;

use Derafu\Backbone\Contract\WorkerInterface;
use Derafu\BackboneDispatcher\Contract\InspectorInterface;
use Derafu\BackboneDispatcher\Exception\InvalidParameterTypeException;
use Derafu\BackboneDispatcher\Exception\MissingParameterException;

/**
 * Resolves the parameters passed to an operation (a public method of a
 * worker).
 *
 * It is responsible for assigning the parameters in the correct order and
 * validating them. It is transport-agnostic: the caller is responsible for
 * extracting the raw parameters (e.g. from a JSON HTTP body, from a Python
 * dict, from CLI arguments) before calling resolve().
 */
class Resolver
{
    /**
     * Constructor with dependencies.
     *
     * @param InspectorInterface $inspector
     * @param Caster $caster
     * @param Validator $validator
     */
    public function __construct(
        private InspectorInterface $inspector,
        private Caster $caster,
        private Validator $validator
    ) {
    }

    /**
     * Resolves the input data for an operation of a worker.
     *
     * @param WorkerInterface $workerInstance
     * @param string $operation
     * @param array $requestParameters
     * @return array
     */
    public function resolve(
        WorkerInterface $workerInstance,
        string $operation,
        array $requestParameters
    ): array {
        $parameters = $this->inspector->getOperationParameters($workerInstance, $operation);

        // Validate and get arguments in the correct order.
        $args = [];
        foreach ($parameters as $paramInfo) {
            $name = $paramInfo['name'];
            $required = $paramInfo['required'];
            $default = $paramInfo['default'];

            if ($required && !array_key_exists($name, $requestParameters)) {
                throw MissingParameterException::forParameter($name, $paramInfo['type']);
            }

            $value = $requestParameters[$name] ?? $default;

            if (!$this->validator->validate($value, $paramInfo['type'])) {
                $canBeNull = $value === null && $default === null && !$required;
                if (!$canBeNull) {
                    throw InvalidParameterTypeException::forParameter($name, $paramInfo['type']);
                }
            }

            $type = $this->caster->resolveCastStrategy($paramInfo['type']);
            $args[$name] = $this->caster->cast($value, $type, $paramInfo['type']);
        }

        return $args;
    }
}
