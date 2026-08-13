<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\BackboneDispatcher\Service;

use Derafu\Backbone\Contract\WorkerInterface;
use Derafu\BackboneDispatcher\Exception\InvalidParameterTypeException;
use Derafu\BackboneDispatcher\Exception\MissingParameterException;
use ReflectionMethod;

/**
 * Resolves the parameters passed to a job.
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
     * @param Inspector $inspector
     * @param Caster $caster
     * @param Validator $validator
     */
    public function __construct(
        private Inspector $inspector,
        private Caster $caster,
        private Validator $validator
    ) {
    }

    /**
     * Resolves the input data for a job of a worker.
     *
     * @param WorkerInterface $workerInstance
     * @param string $job
     * @param array $requestParameters
     * @return array
     */
    public function resolve(
        WorkerInterface $workerInstance,
        string $job,
        array $requestParameters
    ): array {
        $method = new ReflectionMethod($workerInstance, $job);
        $parameters = $this->inspector->getParameters($method);

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

            $type = $this->caster->resolveType($paramInfo['type']);
            $args[$name] = $this->caster->cast($value, $type, $paramInfo['type']);
        }

        return $args;
    }
}
