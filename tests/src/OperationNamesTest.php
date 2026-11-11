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

use Derafu\Backbone\Attribute\Operation;
use Derafu\BackboneDispatcher\Contract\OperationPolicyInterface;
use Derafu\BackboneDispatcher\Exception\OperationNotAllowedException;
use Derafu\BackboneDispatcher\Exception\OperationNotFoundException;
use Derafu\BackboneDispatcher\Service\Deserialization\ObjectFactoryRegistry;
use Derafu\BackboneDispatcher\Service\Discovery\Explorer;
use Derafu\BackboneDispatcher\Service\Dispatch\DirectDispatcher;
use Derafu\BackboneDispatcher\Service\Policy\AllowAllOperationPolicy;
use Derafu\BackboneDispatcher\Service\Policy\AllowListOperationPolicy;
use Derafu\BackboneDispatcher\Service\Policy\TaggedOperationPolicy;
use Derafu\BackboneDispatcher\Service\Reflection\CachedInspector;
use Derafu\BackboneDispatcher\Service\Reflection\Inspector;
use Derafu\BackboneDispatcher\Service\Resolution\Caster;
use Derafu\BackboneDispatcher\Service\Resolution\Resolver;
use Derafu\BackboneDispatcher\Service\Resolution\Validator;
use Derafu\BackboneDispatcher\Translation\BackboneDispatcherTranslationResourceProvider;
use Derafu\Cache\Enum\LocalCacheBackend;
use Derafu\Cache\LocalCacheFactory;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleComponent;
use Derafu\TestsBackboneDispatcher\Fixture\ExamplePackage;
use Derafu\TestsBackboneDispatcher\Fixture\ExamplePackageRegistry;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleWorker;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;
use Derafu\Translation\TranslatorFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Inspector::class)]
#[CoversClass(CachedInspector::class)]
#[CoversClass(DirectDispatcher::class)]
#[CoversClass(Explorer::class)]
#[CoversClass(TaggedOperationPolicy::class)]
#[CoversClass(AllowListOperationPolicy::class)]
#[UsesClass(Resolver::class)]
#[UsesClass(Caster::class)]
#[UsesClass(Validator::class)]
#[UsesClass(ObjectFactoryRegistry::class)]
#[UsesClass(AllowAllOperationPolicy::class)]
#[UsesClass(OperationNotFoundException::class)]
#[UsesClass(OperationNotAllowedException::class)]
#[UsesClass(BackboneDispatcherTranslationResourceProvider::class)]
final class OperationNamesTest extends TestCase
{
    private Inspector $inspector;

    private NamedWorkerForOperationTest $worker;

    private ExamplePackageRegistry $registry;

    protected function setUp(): void
    {
        // Inherited attributes must resolve the same way as declared ones.
        $this->worker = new class () extends NamedWorkerForOperationTest {};
        $this->inspector = new Inspector();
        $this->registry = new ExamplePackageRegistry();
        $this->registry->registerPackage('example_package', new ExamplePackage([
            'example_component' => new ExampleComponent(['example_worker' => $this->worker]),
        ]));
    }

    private function dispatcher(OperationPolicyInterface $policy): DirectDispatcher
    {
        return new DirectDispatcher(
            $this->registry,
            $this->inspector,
            new Resolver($this->inspector, new Caster(new ObjectFactoryRegistry()), new Validator()),
            $policy,
        );
    }

    public function testDiscoveryPublishesTheExternalNameAndIndependentSummary(): void
    {
        $explorer = new Explorer($this->registry, $this->inspector);
        $operation = $explorer->getOperation('example_package', 'example_component', 'example_worker', 'create_draft');

        $this->assertSame('example_package.example_component.example_worker::create_draft', $operation['id']);
        $this->assertSame('create_draft', $operation['name']);
        $this->assertSame('Create a draft', $operation['summary']);
        $names = array_column($explorer->getOperations('example_package', 'example_component', 'example_worker'), 'name');
        $this->assertContains('create_draft', $names);
        $this->assertNotContains('createDraft', $names);
        $this->assertSame('createDraft', $this->inspector->getOperationMethod($this->worker, 'create_draft'));
        $this->assertNull($this->inspector->getOperationMethod($this->worker, 'createDraft'));
        $this->assertTrue($this->inspector->isOperation($this->worker, 'create_draft'));
        $this->assertFalse($this->inspector->hasOperationAttribute($this->worker, 'createDraft'));
    }

    public function testTaggedPolicyAndParameterResolutionUseTheNamedOperation(): void
    {
        $policy = new TaggedOperationPolicy($this->registry, $this->inspector);
        $this->assertTrue($policy->isAllowed('example_package', 'example_component', 'example_worker', 'create_draft'));
        $this->assertFalse($policy->isAllowed('example_package', 'example_component', 'example_worker', 'createDraft'));
        $result = $this->dispatcher($policy)->dispatch('example_package', 'example_component', 'example_worker', 'create_draft', ['amount' => 7]);
        $this->assertSame(7, $result);
        $this->assertSame(7, $this->worker->createDraft(7));
    }

    public function testAllowListMatchesTheExternalIdentifier(): void
    {
        $policy = new AllowListOperationPolicy(['example_package.example_component.example_worker::create_draft']);
        $this->assertSame(7, $this->dispatcher($policy)->dispatch('example_package', 'example_component', 'example_worker', 'create_draft', ['amount' => 7]));

        $explorer = new Explorer($this->registry, $this->inspector, $policy);
        $this->assertSame(['create_draft'], array_column($explorer->getOperations('example_package', 'example_component', 'example_worker'), 'name'));

        $this->expectException(OperationNotAllowedException::class);
        $this->dispatcher(new AllowListOperationPolicy(['*::createDraft']))->dispatch('example_package', 'example_component', 'example_worker', 'create_draft', ['amount' => 7]);
    }

    #[TestWith(['createDraft'])]
    #[TestWith(['CREATE_DRAFT'])]
    public function testTheExplicitNameIsTheOnlyExternalIdentifier(string $name): void
    {
        $this->expectException(OperationNotFoundException::class);
        $this->dispatcher(new AllowAllOperationPolicy())->dispatch('example_package', 'example_component', 'example_worker', $name, ['amount' => 7]);
    }

    public function testDiscoveryRejectsThePhpNameOfANamedOperation(): void
    {
        $this->expectException(OperationNotFoundException::class);
        (new Explorer($this->registry, $this->inspector))->getOperation('example_package', 'example_component', 'example_worker', 'createDraft');
    }

    public function testDefaultNamesRetainPhpMethodResolution(): void
    {
        $this->assertSame('sum', $this->inspector->getOperationMethod($this->worker, 'SUM'));
        $this->assertSame(7, $this->dispatcher(new AllowAllOperationPolicy())->dispatch('example_package', 'example_component', 'example_worker', 'SUM', ['a' => 3, 'b' => 4]));
        $this->assertNull($this->inspector->getOperationMethod($this->worker, 'missing'));
    }

    public function testCachedMetadataRetainsTheExternalNameAndSummary(): void
    {
        $cached = new CachedInspector($this->inspector, LocalCacheFactory::pool(LocalCacheBackend::Memory, 'operation_names'));
        $methods = $cached->getPublicMethods($this->worker);
        $this->assertSame($methods, $cached->getPublicMethods($this->worker));
        $this->assertNotNull($methods['create_draft']['operation']);
        $this->assertSame('create_draft', $methods['create_draft']['operation']['name']);
        $this->assertSame('Create a draft', $methods['create_draft']['operation']['summary']);
        $this->assertSame('createDraft', $cached->getOperationMethod($this->worker, 'create_draft'));
        $this->assertNull($cached->getOperationMethod($this->worker, 'createDraft'));
        $this->assertTrue($cached->hasOperationAttribute($this->worker, 'create_draft'));
        $this->assertTrue($cached->isOperation($this->worker, 'create_draft'));
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testDuplicateExternalNamesAreRejectedInDiscoveryAndResolution(bool $resolve): void
    {
        $service = new class () {
            #[Operation(name: 'duplicate')]
            public function first(): void
            {
            }

            #[Operation(name: 'duplicate')]
            public function second(): void
            {
            }
        };

        try {
            if ($resolve) {
                $this->inspector->getOperationMethod($service, 'duplicate');
            } else {
                $this->inspector->getPublicMethods($service);
            }
            $this->fail('Duplicate operation names must be rejected.');
        } catch (InvalidArgumentException $exception) {
            $translator = TranslatorFactory::create('es', resourceProviders: [new BackboneDispatcherTranslationResourceProvider()]);
            $this->assertStringContainsString('"duplicate"', $exception->getMessage());
            $this->assertStringContainsString('first', $exception->getMessage());
            $this->assertStringContainsString('second', $exception->getMessage());
            $this->assertStringContainsString('El nombre de operación "duplicate"', $exception->trans($translator));
        }
    }

    public function testAnExternalNameCannotCollideWithAnUnnamedMethod(): void
    {
        $service = new class () {
            #[Operation(name: 'existing')]
            public function renamed(): void
            {
            }

            public function existing(): void
            {
            }
        };
        $this->expectException(InvalidArgumentException::class);
        $this->inspector->getOperationMethod($service, 'existing');
    }
}

class NamedWorkerForOperationTest extends ExampleWorker
{
    #[Operation(name: 'create_draft', summary: 'Create a draft')]
    public function createDraft(int $amount): int
    {
        return $amount;
    }
}
