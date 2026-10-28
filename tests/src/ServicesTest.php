<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsBackboneDispatcher;

use Composer\InstalledVersions;
use Derafu\BackboneDispatcher\Contract\DirectDispatcherInterface;
use Derafu\BackboneDispatcher\Contract\ExplorerInterface;
use Derafu\BackboneDispatcher\Contract\InspectorInterface;
use Derafu\BackboneDispatcher\Contract\ObjectFactoryInterface;
use Derafu\BackboneDispatcher\Contract\SafeDispatcherInterface;
use Derafu\BackboneDispatcher\Contract\SafeExplorerInterface;
use Derafu\BackboneDispatcher\Contract\TypedDispatcherInterface;
use Derafu\BackboneDispatcher\Exception\InvalidOperationIdException;
use Derafu\BackboneDispatcher\Exception\OperationNotAllowedException;
use Derafu\BackboneDispatcher\Translation\BackboneDispatcherTranslationResourceProvider;
use Derafu\BackboneDispatcher\ValueObject\OperationRequest;
use Derafu\Cache\Contract\CacheKeyInterface;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleBag;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleGreeting;
use Derafu\TestsBackboneDispatcher\Fixture\WiringWorker;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use RuntimeException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

/**
 * Exercises dispatch, discovery and consumer contributions through the real YAML.
 */
#[CoversNothing]
final class ServicesTest extends TestCase
{
    private function load(string $configuration = 'services.yaml'): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $root = dirname(__DIR__, 2);
        $container->setParameter('kernel.project_dir', $root);
        (new YamlFileLoader($container, new FileLocator([
            $root . '/tests/fixtures/config',
            (InstalledVersions::getInstallPath('derafu/cache')
                ?? throw new RuntimeException('derafu/cache must be installed for these tests.')) . '/resources/config',
        ])))
            ->load($configuration);
        foreach ($container->getDefinitions() as $definition) {
            $definition->setPublic(true);
        }

        return $container;
    }

    public function testTheConfiguredProviderCanBeConstructed(): void
    {
        $container = $this->load();
        $container->getDefinition(BackboneDispatcherTranslationResourceProvider::class)->setPublic(true);
        $container->compile();
        $provider = $container->get(BackboneDispatcherTranslationResourceProvider::class);
        $this->assertInstanceOf(BackboneDispatcherTranslationResourceProvider::class, $provider);
        $directories = [...$provider->getDirectories()];
        $this->assertCount(1, $directories);
        $this->assertSame(realpath(dirname(__DIR__, 2) . '/resources/translations'), realpath($directories[0]));
    }

    public function testTaggedResourcesTranslateAnInvalidOperationIdentifier(): void
    {
        $container = $this->load();
        $this->assertArrayHasKey(
            BackboneDispatcherTranslationResourceProvider::class,
            $container->findTaggedServiceIds('derafu_translation.resource_provider')
        );
        $container->compile();
        $translator = $container->get('app.translator');
        $this->assertInstanceOf(Translator::class, $translator);

        try {
            OperationRequest::fromId('billing::create');
            $this->fail('An operation identifier without a component and worker was accepted.');
        } catch (InvalidOperationIdException $exception) {
            $this->assertSame(
                'The operation id billing::create is not valid. It must have the structure: package.component.worker::operation.',
                $exception->getMessage()
            );
            $this->assertSame(
                'El id de operación billing::create no es válido. Debe tener la estructura: package.component.worker::operation.',
                $exception->trans($translator)
            );
        }
    }

    public function testConfiguredDispatchersResolveTypedParametersAndWrapResults(): void
    {
        $container = $this->load();
        $container->compile();
        $direct = $container->get(DirectDispatcherInterface::class);
        $this->assertInstanceOf(DirectDispatcherInterface::class, $direct);
        $this->assertSame(15, $direct->dispatch('example_package', 'example_component', 'example_worker', 'sum', ['a' => 5]));
        $request = OperationRequest::fromId('example_package.example_component.example_worker::sum', ['a' => 5]);
        $typed = $container->get(TypedDispatcherInterface::class);
        $this->assertInstanceOf(TypedDispatcherInterface::class, $typed);
        $this->assertSame(15, $typed->dispatch($request)->getValue());
        $safe = $container->get(SafeDispatcherInterface::class);
        $this->assertInstanceOf(SafeDispatcherInterface::class, $safe);
        $result = $safe->dispatch($request);
        $this->assertTrue($result->isSuccess());
        $this->assertSame(15, $result->getValue());
        $this->assertSame('integer', $result->getDataType());
    }

    public function testDiscoveryAndDispatchUseTheSameAttributePolicy(): void
    {
        $container = $this->load();
        $container->compile();
        $explorer = $container->get(ExplorerInterface::class);
        $this->assertInstanceOf(ExplorerInterface::class, $explorer);
        $operations = $explorer->getOperations('example_package', 'example_component', 'example_worker');
        $this->assertCount(4, $operations);
        $safeExplorer = $container->get(SafeExplorerInterface::class);
        $this->assertInstanceOf(SafeExplorerInterface::class, $safeExplorer);
        $this->assertTrue($safeExplorer->getOperations('example_package', 'example_component', 'example_worker')->isSuccess());
        $direct = $container->get(DirectDispatcherInterface::class);
        $this->assertInstanceOf(DirectDispatcherInterface::class, $direct);
        $this->expectException(OperationNotAllowedException::class);
        $direct->dispatch('example_package', 'example_component', 'example_worker', 'getName');
    }

    public function testDefaultFactoryUsesTheFromArrayConvention(): void
    {
        $container = $this->load();
        $container->compile();
        $factory = $container->get(ObjectFactoryInterface::class);
        $this->assertInstanceOf(ObjectFactoryInterface::class, $factory);
        $bag = $factory->create(['name' => 'Invoice', 'amount' => 7], ExampleBag::class);
        $this->assertInstanceOf(ExampleBag::class, $bag);
        $this->assertSame('Invoice', $bag->getName());
        $this->assertSame(7, $bag->getAmount());
    }

    public function testTaggedDeserializerIsUsedInsteadOfTheFallback(): void
    {
        $container = $this->load('deserializers.yaml');
        $container->compile();
        $dispatcher = $container->get(SafeDispatcherInterface::class);
        $this->assertInstanceOf(SafeDispatcherInterface::class, $dispatcher);
        $result = $dispatcher->dispatch(OperationRequest::fromId(
            'example_package.example_component.example_worker::describeBag',
            ['bag' => ['name' => 'Invoice', 'amount' => 7]]
        ));
        $this->assertTrue($result->isSuccess());
        $this->assertSame(['name' => 'Invoice (via registry)', 'doubled' => 14], $result->getValue());
    }

    public function testDeserializerCanReuseItsOwnFactoryForNestedObjects(): void
    {
        $container = $this->load('deserializers.yaml');
        $container->compile();
        $dispatcher = $container->get(SafeDispatcherInterface::class);
        $this->assertInstanceOf(SafeDispatcherInterface::class, $dispatcher);
        $result = $dispatcher->dispatch(OperationRequest::fromId(
            'example_package.example_component.example_worker::greet',
            ['greeting' => ['message' => 'Hello', 'reply' => ['message' => 'Welcome']]]
        ));
        $this->assertTrue($result->isSuccess());
        $this->assertSame(['message' => 'Hello', 'reply' => ['message' => 'Welcome', 'reply' => null]], $result->getValue());
    }

    public function testSafeServicesShareConfiguredErrorMetadata(): void
    {
        $container = $this->load('debug.yaml');
        $container->compile();
        $dispatcher = $container->get(SafeDispatcherInterface::class);
        $this->assertInstanceOf(SafeDispatcherInterface::class, $dispatcher);
        $failed = $dispatcher->dispatch(OperationRequest::fromId('example_package.example_component.example_worker::fail'));
        $this->assertFalse($failed->isSuccess());
        $problem = $failed->getProblem();
        $this->assertNotNull($problem);
        $this->assertSame('test', $problem->getEnvironment());
        $this->assertTrue($problem->isDebug());
        $explorer = $container->get(SafeExplorerInterface::class);
        $this->assertInstanceOf(SafeExplorerInterface::class, $explorer);
        $missing = $explorer->getPackages();
        $this->assertTrue($missing->isSuccess());
        $missing = $explorer->getComponents('missing_package');
        $this->assertFalse($missing->isSuccess());
        $problem = $missing->getProblem();
        $this->assertNotNull($problem);
        $this->assertSame('test', $problem->getEnvironment());
        $this->assertTrue($problem->isDebug());
    }

    public function testOptionalInspectorDecoratorUsesTheApplicationPoolAndKeyService(): void
    {
        $container = $this->load('cache.yaml');
        $container->compile();
        $inspector = $container->get(InspectorInterface::class);
        $this->assertInstanceOf(InspectorInterface::class, $inspector);
        $worker = $container->get(WiringWorker::class);
        $this->assertInstanceOf(WiringWorker::class, $worker);
        $keys = $container->get(CacheKeyInterface::class);
        $this->assertInstanceOf(CacheKeyInterface::class, $keys);
        $pool = $container->get(CacheItemPoolInterface::class);
        $this->assertInstanceOf(CacheItemPoolInterface::class, $pool);
        $key = $keys->build('backbone_dispatcher.class_doc', $worker::class);
        $this->assertFalse($pool->getItem($key)->isHit());
        $doc = $inspector->getClassDoc($worker);
        $item = $pool->getItem($key);
        $this->assertTrue($item->isHit());
        $this->assertSame($doc, $item->get());
        $doc['summary'] = 'Read from the application cache';
        $this->assertTrue($pool->save($item->set($doc)));
        $this->assertSame($doc, $inspector->getClassDoc($worker));
    }

    public function testInspectorTtlCanDisableTheOptionalCache(): void
    {
        $container = $this->load('cache-disabled.yaml');
        $container->compile();
        $inspector = $container->get(InspectorInterface::class);
        $this->assertInstanceOf(InspectorInterface::class, $inspector);
        $worker = $container->get(WiringWorker::class);
        $this->assertInstanceOf(WiringWorker::class, $worker);
        $inspector->getClassDoc($worker);
        $pool = $container->get(CacheItemPoolInterface::class);
        $this->assertInstanceOf(CacheItemPoolInterface::class, $pool);
        $keys = $container->get(CacheKeyInterface::class);
        $this->assertInstanceOf(CacheKeyInterface::class, $keys);
        $this->assertFalse($pool->getItem($keys->build('backbone_dispatcher.class_doc', $worker::class))->isHit());
    }

    public function testApplicationPolicyOverrideReachesBothDiscoveryAndExecution(): void
    {
        $container = $this->load('allow-list.yaml');
        $container->compile();
        $explorer = $container->get(ExplorerInterface::class);
        $this->assertInstanceOf(ExplorerInterface::class, $explorer);
        $operations = $explorer->getOperations('example_package', 'example_component', 'example_worker');
        $this->assertCount(1, $operations);
        $this->assertSame('makeGreeting', $operations[0]['name']);
        $dispatcher = $container->get(DirectDispatcherInterface::class);
        $this->assertInstanceOf(DirectDispatcherInterface::class, $dispatcher);
        $greeting = $dispatcher->dispatch('example_package', 'example_component', 'example_worker', 'makeGreeting', ['name' => 'User']);
        $this->assertInstanceOf(ExampleGreeting::class, $greeting);
        $this->assertSame('Hello, User!', $greeting->getMessage());
        $this->expectException(OperationNotAllowedException::class);
        $dispatcher->dispatch('example_package', 'example_component', 'example_worker', 'sum', ['a' => 5]);
    }
}
