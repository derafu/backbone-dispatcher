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

use Derafu\BackboneDispatcher\Exception\InvalidOperationIdException;
use Derafu\BackboneDispatcher\Translation\BackboneDispatcherTranslationResourceProvider;
use Derafu\BackboneDispatcher\ValueObject\OperationRequest;
use Derafu\Translation\TranslatorFactory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

/**
 * Import the provider-only YAML and consume its tagged translation resources.
 */
#[CoversNothing]
final class ServicesTest extends TestCase
{
    private function load(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new YamlFileLoader($container, new FileLocator(dirname(__DIR__, 2) . '/resources/config')))
            ->load('backbone-dispatcher-services.yaml');

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
        $container->register('app.translator', Translator::class)
            ->setFactory([TranslatorFactory::class, 'create'])
            ->setArguments(['es', ['en'], new TaggedIteratorArgument('derafu_translation.resource_provider')])
            ->setPublic(true);
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
}
