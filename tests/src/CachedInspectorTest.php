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

use Derafu\BackboneDispatcher\Contract\InspectorInterface;
use Derafu\BackboneDispatcher\Service\Reflection\CachedInspector;
use Derafu\BackboneDispatcher\Service\Reflection\Inspector;
use Derafu\Cache\Adapter\PhpFilesCache;
use Derafu\Cache\Enum\LocalCacheBackend;
use Derafu\Cache\LocalCacheFactory;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleWorker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CachedInspector::class)]
#[UsesClass(Inspector::class)]
class CachedInspectorTest extends TestCase
{
    private ExampleWorker $worker;

    protected function setUp(): void
    {
        $this->worker = new ExampleWorker();
    }

    public function testWorksAgainstARealFileBackedPsr6Pool(): void
    {
        // Uses a real, file-backed PSR-6 pool (derafu/cache's PhpFilesCache)
        // instead of a mock, to confirm CachedInspector works end to end
        // against an actual disk-backed cache, not just ArrayAdapter.
        $directory = sys_get_temp_dir() . '/backbone_dispatcher_test_' . uniqid();
        $pool = new PhpFilesCache('backbone_dispatcher_test', $directory);

        try {
            $cached = new CachedInspector(new Inspector(), $pool);

            $doc = $cached->getClassDoc($this->worker);

            $this->assertSame(ExampleWorker::class, $doc['name']);
        } finally {
            $pool->clear();
        }
    }

    public function testGetClassDocIsOnlyComputedOnceAcrossSeveralCalls(): void
    {
        $real = $this->createMock(InspectorInterface::class);
        $real->expects($this->once())
            ->method('getClassDoc')
            ->willReturn(['name' => ExampleWorker::class]);

        $cached = new CachedInspector($real, LocalCacheFactory::pool(LocalCacheBackend::Memory, 'test'));

        $cached->getClassDoc($this->worker);
        $cached->getClassDoc($this->worker);
        $cached->getClassDoc($this->worker);
    }

    public function testGetPublicMethodsIsOnlyComputedOnceAcrossSeveralCalls(): void
    {
        $real = $this->createMock(InspectorInterface::class);
        $real->expects($this->once())
            ->method('getPublicMethods')
            ->willReturn(['sum' => ['name' => 'sum']]);

        $cached = new CachedInspector($real, LocalCacheFactory::pool(LocalCacheBackend::Memory, 'test'));

        $cached->getPublicMethods($this->worker);
        $cached->getPublicMethods($this->worker);
    }

    public function testDifferentFiltersAreCachedSeparately(): void
    {
        $real = $this->createMock(InspectorInterface::class);
        $real->expects($this->exactly(2))
            ->method('getPublicMethods')
            ->willReturnMap([
                [$this->worker, [], ['sum' => [], 'describeBag' => []]],
                [$this->worker, ['operation' => true], ['sum' => []]],
            ]);

        $cached = new CachedInspector($real, LocalCacheFactory::pool(LocalCacheBackend::Memory, 'test'));

        $cached->getPublicMethods($this->worker);
        $cached->getPublicMethods($this->worker, ['operation' => true]);
        // Repeating both must not trigger a third/fourth real computation.
        $cached->getPublicMethods($this->worker);
        $cached->getPublicMethods($this->worker, ['operation' => true]);
    }

    public function testGetTaggedOperationsGoesThroughTheCachedGetPublicMethods(): void
    {
        $real = $this->createMock(InspectorInterface::class);
        $real->expects($this->once())
            ->method('getPublicMethods')
            ->with($this->worker, ['operation' => true])
            ->willReturn(['sum' => ['name' => 'sum']]);

        $cached = new CachedInspector($real, LocalCacheFactory::pool(LocalCacheBackend::Memory, 'test'));

        $this->assertSame(['sum' => ['name' => 'sum']], $cached->getTaggedOperations($this->worker));
        // Second call must hit the cache, not the real inspector again.
        $cached->getTaggedOperations($this->worker);
    }

    public function testTtlOfZeroBypassesTheCacheEntirely(): void
    {
        $real = $this->createMock(InspectorInterface::class);
        $real->expects($this->exactly(2))
            ->method('getClassDoc')
            ->willReturn(['name' => ExampleWorker::class]);

        $cached = new CachedInspector($real, LocalCacheFactory::pool(LocalCacheBackend::Memory, 'test'), ttl: 0);

        $cached->getClassDoc($this->worker);
        $cached->getClassDoc($this->worker);
    }

    public function testIsOperationHasOperationAttributeAndGetOperationParametersAreNeverCached(): void
    {
        $real = $this->createMock(InspectorInterface::class);
        $real->expects($this->exactly(2))->method('isOperation')->willReturn(true);
        $real->expects($this->exactly(2))->method('hasOperationAttribute')->willReturn(true);
        $real->expects($this->exactly(2))->method('getOperationParameters')->willReturn([]);

        $cached = new CachedInspector($real, LocalCacheFactory::pool(LocalCacheBackend::Memory, 'test'));

        $cached->isOperation($this->worker, 'sum');
        $cached->isOperation($this->worker, 'sum');
        $cached->hasOperationAttribute($this->worker, 'sum');
        $cached->hasOperationAttribute($this->worker, 'sum');
        $cached->getOperationParameters($this->worker, 'sum');
        $cached->getOperationParameters($this->worker, 'sum');
    }
}
