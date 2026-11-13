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

use Derafu\Backbone\Contract\WorkerInterface;
use Derafu\BackboneDispatcher\Contract\ObjectFactoryInterface;
use Derafu\BackboneDispatcher\Contract\SafeDispatcherInterface;
use Derafu\BackboneDispatcher\Contract\SafeExplorerInterface;
use Derafu\BackboneDispatcher\Service\Deserialization\ObjectFactoryRegistry;
use Derafu\BackboneDispatcher\Service\Discovery\Explorer;
use Derafu\BackboneDispatcher\Service\Discovery\SafeExplorer;
use Derafu\BackboneDispatcher\Service\Dispatch\DirectDispatcher;
use Derafu\BackboneDispatcher\Service\Dispatch\SafeDispatcher;
use Derafu\BackboneDispatcher\Service\Dispatch\TypedDispatcher;
use Derafu\BackboneDispatcher\Service\Policy\TaggedOperationPolicy;
use Derafu\BackboneDispatcher\Service\Reflection\Inspector;
use Derafu\BackboneDispatcher\Service\Resolution\Caster;
use Derafu\BackboneDispatcher\Service\Resolution\Resolver;
use Derafu\BackboneDispatcher\Service\Resolution\Validator;
use Derafu\BackboneDispatcher\Service\Serialization\Serializer;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleBag;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleBagDeserializer;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleComponent;
use Derafu\TestsBackboneDispatcher\Fixture\ExamplePackage;
use Derafu\TestsBackboneDispatcher\Fixture\ExamplePackageRegistry;

/**
 * Builds the real services of a small dispatcher for the tests of the harness:
 * one that follows every rule of the conformance suite and one that breaks
 * them.
 */
final class HarnessServices
{
    /**
     * The object factory of the dispatcher that follows the rules: it builds
     * the documents and the bags.
     */
    public static function objectFactory(): ObjectFactoryInterface
    {
        return new ObjectFactoryRegistry([
            HarnessDocument::class => new HarnessDocumentDeserializer(),
            ExampleBag::class => new ExampleBagDeserializer(),
        ]);
    }

    /**
     * The object factory of the dispatcher that breaks the rules: it builds
     * nothing.
     */
    public static function emptyObjectFactory(): ObjectFactoryInterface
    {
        return new ObjectFactoryRegistry();
    }

    /**
     * The dispatcher with the worker that follows the rules.
     */
    public static function dispatcher(): SafeDispatcherInterface
    {
        $registry = self::registry(new HarnessWorker(), 'harness_worker');
        $inspector = new Inspector();

        return new SafeDispatcher(
            new TypedDispatcher(new DirectDispatcher(
                $registry,
                $inspector,
                new Resolver($inspector, new Caster(self::objectFactory()), new Validator()),
                new TaggedOperationPolicy($registry, $inspector),
            )),
            new Serializer(),
            environment: 'test',
            debug: true,
        );
    }

    /**
     * The explorer of the dispatcher with the worker that follows the rules.
     */
    public static function explorer(): SafeExplorerInterface
    {
        return self::safeExplorer(self::registry(new HarnessWorker(), 'harness_worker'));
    }

    /**
     * The explorer of the dispatcher with the worker that breaks the rules.
     */
    public static function badExplorer(): SafeExplorerInterface
    {
        return self::safeExplorer(self::registry(new BadWorker(), 'bad_worker'));
    }

    private static function safeExplorer(ExamplePackageRegistry $registry): SafeExplorerInterface
    {
        $inspector = new Inspector();

        return new SafeExplorer(
            new Explorer($registry, $inspector, new TaggedOperationPolicy($registry, $inspector)),
            environment: 'test',
            debug: true,
        );
    }

    private static function registry(WorkerInterface $worker, string $name): ExamplePackageRegistry
    {
        $component = new ExampleComponent([$name => $worker]);
        $package = new ExamplePackage(['example_component' => $component]);

        return (new ExamplePackageRegistry())->registerPackage('example_package', $package);
    }
}
