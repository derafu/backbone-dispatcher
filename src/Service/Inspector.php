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

use Derafu\Backbone\Attribute\ApiResource;
use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlock\Tags\Param;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\DocBlockFactoryInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;
use Symfony\Component\VarExporter\LazyObjectInterface;

/**
 * Class for inspecting classes using "Reflection".
 */
class Inspector
{
    /**
     * Gets the documentation of a class.
     *
     * @param object $service
     * @return array
     */
    public function getClassDoc(object $service): array
    {
        $reflection = new ReflectionClass($service);

        if ($service instanceof LazyObjectInterface) {
            $reflection = $reflection->getParentClass();
        }

        $docComment = $reflection->getDocComment();
        $docBlock = $docComment
            ? DocBlockFactory::createInstance()->create($docComment)
            : null
        ;

        // Resolve inheritance of documentation.
        if (
            $docBlock
            && (
                $docBlock->getSummary() === '{@inheritDoc}'
                || $docBlock->getDescription()->render() === '{@inheritDoc}'
            )
        ) {
            $docBlock = $this->resolveInheritedClassDoc($reflection, DocBlockFactory::createInstance());
        }

        return [
            'name' => $reflection->getName(),
            'summary' => $docBlock?->getSummary() ?: null,
            'description' => $docBlock?->getDescription()->render() ?: null,
            'tags' => $this->getDocBlockTags($docBlock),
            'links' => $this->getDocBlockLinks($docBlock),
        ];
    }

    /**
     * Gets the API resources of a class.
     *
     * @param object $service
     * @return array
     */
    public function getApiResources(object $service): array
    {
        return $this->getPublicMethods($service, ['api_resource' => true]);
    }

    /**
     * Gets the public methods of a class with its parameters.
     *
     * @param object $service
     * @param array $filters
     * @return array
     */
    public function getPublicMethods(object $service, array $filters = []): array
    {
        $reflection = new ReflectionClass($service);

        if ($service instanceof LazyObjectInterface) {
            $reflection = $reflection->getParentClass();
        }

        $methods = [];
        $docBlockFactory = DocBlockFactory::createInstance();

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }

            $apiResourceAttribute = null;
            if (!empty($filters['api_resource'])) {
                $attributes = $method->getAttributes(ApiResource::class);
                if (empty($attributes)) {
                    continue;
                }
                $apiResourceAttribute = $attributes[0]->newInstance();
            }

            $name = $method->getName();

            if ($name[0] === '_') {
                continue;
            }

            $docComment = $method->getDocComment();
            $docBlock = $docComment
                ? $docBlockFactory->create($docComment)
                : null
            ;

            // Resolve inheritance of documentation.
            if (
                $docBlock
                && (
                    $docBlock->getSummary() === '{@inheritDoc}'
                    || $docBlock->getDescription()->render() === '{@inheritDoc}'
                )
            ) {
                $docBlock = $this->resolveInheritedMethodDoc($method, $docBlockFactory);
            }

            $tags = $this->getDocBlockTags($docBlock);

            $methods[$name] = [
                'name' => $name,
                'summary' => $docBlock?->getSummary() ?: null,
                'description' => $docBlock?->getDescription()->render() ?: null,
                'parameters' => $this->getParameters($method, $docBlock),
                'tags' => $tags,
                'links' => $this->getDocBlockLinks($docBlock),
                'deprecated' => !empty($tags['deprecated']),
                'apiResource' => $apiResourceAttribute?->toArray(),
            ];
        }

        return $methods;
    }

    /**
     * Gets the information of the parameters of a method.
     *
     * @param ReflectionMethod $method
     * @param DocBlock|null $docBlock
     * @return array
     */
    public function getParameters(
        ReflectionMethod $method,
        ?DocBlock $docBlock = null
    ): array {
        $parametersInfo = [];
        $docParams = $docBlock ? $docBlock->getTagsByName('param') : [];

        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();

            // Resolve the type (handles Union Types).
            if ($type instanceof ReflectionUnionType) {
                $types = [];
                foreach ($type->getTypes() as $unionType) {
                    $types[] = $unionType->getName();
                }
                $typeName = implode('|', $types);
            } elseif ($type instanceof ReflectionNamedType) {
                $typeName = $type->getName();
            } else {
                $typeName = 'mixed';
            }

            $docParam = null;
            foreach ($docParams as $tag) {
                assert($tag instanceof Param);
                if ($tag->getVariableName() === $parameter->getName()) {
                    $docParam = $tag;
                    break;
                }
            }

            $paramInfo = [
                'name' => $parameter->getName(),
                'type' => $typeName,
                'required' => !$parameter->isOptional(),
                'default' => $parameter->isDefaultValueAvailable()
                    ? $parameter->getDefaultValue()
                    : null
                ,
                'description' => $docParam?->getDescription()->render(),
            ];

            $parametersInfo[] = $paramInfo;
        }

        return $parametersInfo;
    }

    /**
     * Resolves the inherited documentation of a parent method or interface.
     *
     * @param ReflectionMethod $method
     * @param DocBlockFactoryInterface $docBlockFactory
     * @return DocBlock|null
     */
    private function resolveInheritedMethodDoc(
        ReflectionMethod $method,
        DocBlockFactoryInterface $docBlockFactory
    ): ?DocBlock {
        // Search documentation in the parent class.
        $parentClass = $method->getDeclaringClass()->getParentClass();
        if ($parentClass && $parentClass->hasMethod($method->getName())) {
            $parentMethod = $parentClass->getMethod($method->getName());
            $parentDocComment = $parentMethod->getDocComment();
            if ($parentDocComment) {
                return $docBlockFactory->create($parentDocComment);
            }
        }

        // Search documentation in implemented interfaces.
        foreach ($method->getDeclaringClass()->getInterfaces() as $interface) {
            if ($interface->hasMethod($method->getName())) {
                $interfaceMethod = $interface->getMethod($method->getName());
                $interfaceDocComment = $interfaceMethod->getDocComment();
                if ($interfaceDocComment) {
                    return $docBlockFactory->create($interfaceDocComment);
                }
            }
        }

        return null;
    }

    /**
     * Resolves the inherited documentation of a parent class.
     *
     * @param ReflectionClass $reflection
     * @param DocBlockFactoryInterface $docBlockFactory
     * @return DocBlock|null
     */
    private function resolveInheritedClassDoc(
        ReflectionClass $reflection,
        DocBlockFactoryInterface $docBlockFactory
    ): ?DocBlock {
        // Search documentation in the parent class.
        $parentClass = $reflection->getParentClass();
        if ($parentClass) {
            $parentDocComment = $parentClass->getDocComment();
            if ($parentDocComment) {
                return $docBlockFactory->create($parentDocComment);
            }
        }

        // Search documentation in implemented interfaces.
        foreach ($reflection->getInterfaces() as $interface) {
            $interfaceDocComment = $interface->getDocComment();
            if ($interfaceDocComment) {
                return $docBlockFactory->create($interfaceDocComment);
            }
        }

        return null;
    }

    /**
     * Gets the tags from a class DocBlock.
     *
     * @param DocBlock|null $docBlock
     * @return array
     */
    private function getDocBlockTags(?DocBlock $docBlock): array
    {
        if (!$docBlock) {
            return [];
        }

        $tags = [];
        foreach ($docBlock->getTags() as $tag) {
            $tagName = $tag->getName();

            if ($tagName === 'link') {
                continue;
            }

            if (!isset($tags[$tagName])) {
                $tags[$tagName] = [];
            }

            // Convert tag to string representation.
            $tags[$tagName][] = (string) $tag;
        }

        return $tags;
    }

    /**
     * Gets the links from a class DocBlock.
     *
     * @param DocBlock|null $docBlock
     * @return array
     */
    private function getDocBlockLinks(?DocBlock $docBlock): array
    {
        if (!$docBlock) {
            return [];
        }

        $links = [];
        foreach ($docBlock->getTagsByName('link') as $linkTag) {
            // Extract URL and description from link tag.
            $linkString = (string) $linkTag;

            // Parse link format: @link URL Description.
            if (preg_match('/^(\S+)\s+(.+)$/', $linkString, $matches)) {
                $links[] = [
                    'url' => $matches[1],
                    'description' => $matches[2],
                ];
            } else {
                // If no description, just use the URL.
                $links[] = [
                    'url' => $linkString,
                    'description' => null,
                ];
            }
        }

        return $links;
    }
}
