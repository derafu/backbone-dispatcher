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

use Derafu\BackboneDispatcher\Service\Reflection\CachedInspector;
use Derafu\BackboneDispatcher\Service\Reflection\Inspector;
use Derafu\BackboneDispatcher\Translation\BackboneDispatcherTranslationResourceProvider;
use Derafu\Cache\Adapter\PhpFilesCache;
use Derafu\Cache\CacheKey;
use Derafu\Cache\Enum\LocalCacheBackend;
use Derafu\Cache\LocalCacheFactory;
use Derafu\TestsBackboneDispatcher\Fixture\CountingInspector;
use Derafu\TestsBackboneDispatcher\Fixture\DocumentedService;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleWorker;
use Derafu\Translation\Exception\Core\TranslatableLogicException as LogicException;
use Derafu\Translation\TranslatorFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CachedInspector::class)]
#[UsesClass(Inspector::class)]
#[UsesClass(BackboneDispatcherTranslationResourceProvider::class)]
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
        $real = new CountingInspector();
        $cached = new CachedInspector($real, LocalCacheFactory::pool(LocalCacheBackend::Memory, 'test'));

        $doc = $cached->getClassDoc($this->worker);
        $this->assertSame(ExampleWorker::class, $doc['name']);
        $this->assertSame($doc, $cached->getClassDoc($this->worker));
        $cached->getClassDoc($this->worker);
        $this->assertSame(1, $real->calls['getClassDoc']);
    }

    public function testGetPublicMethodsIsOnlyComputedOnceAcrossSeveralCalls(): void
    {
        $real = new CountingInspector();
        $cached = new CachedInspector($real, LocalCacheFactory::pool(LocalCacheBackend::Memory, 'test'));

        $methods = $cached->getPublicMethods($this->worker);
        $this->assertArrayHasKey('sum', $methods);
        $this->assertSame($methods, $cached->getPublicMethods($this->worker));
        $this->assertSame(1, $real->calls['getPublicMethods']);
    }

    public function testTtlOfZeroBypassesTheCacheEntirely(): void
    {
        $real = new CountingInspector();
        $cached = new CachedInspector($real, LocalCacheFactory::pool(LocalCacheBackend::Memory, 'test'), ttl: 0);

        $doc = $cached->getClassDoc($this->worker);
        $this->assertSame($doc, $cached->getClassDoc($this->worker));
        $this->assertSame(2, $real->calls['getClassDoc']);
    }

    public function testIsOperationHasOperationAttributeAndGetOperationParametersAreNeverCached(): void
    {
        $real = new CountingInspector();
        $cached = new CachedInspector($real, LocalCacheFactory::pool(LocalCacheBackend::Memory, 'test'));

        for ($call = 0; $call < 2; ++$call) {
            $this->assertTrue($cached->isOperation($this->worker, 'sum'));
            $this->assertTrue($cached->hasOperationAttribute($this->worker, 'sum'));
            $parameters = $cached->getOperationParameters($this->worker, 'sum');
            $this->assertSame(['a', 'b'], array_column($parameters, 'name'));
        }
        $this->assertSame(2, $real->calls['isOperation']);
        $this->assertSame(2, $real->calls['hasOperationAttribute']);
        $this->assertSame(2, $real->calls['getOperationParameters']);
    }

    /** @return array<string, array{string, string}> */
    public static function cachedMethods(): array
    {
        return [
            'class documentation' => ['getClassDoc', 'backbone_dispatcher.class_doc'],
            'public methods' => ['getPublicMethods', 'backbone_dispatcher.public_methods'],
        ];
    }

    #[DataProvider('cachedMethods')]
    public function testANonArrayCacheHitIsRejectedWithoutReplacingIt(string $method, string $prefix): void
    {
        $pool = LocalCacheFactory::pool(LocalCacheBackend::Memory, 'invalid_inspector_value');
        $key = (new CacheKey())->build($prefix, ExampleWorker::class);
        $pool->save($pool->getItem($key)->set('invalid cached data'));
        $cached = new CachedInspector(new Inspector(), $pool);

        try {
            $cached->$method($this->worker);
            $this->fail('Non-array inspector cache data must be rejected.');
        } catch (LogicException $exception) {
            $this->assertSame('Cached inspector data must be an array.', $exception->getMessage());
            $translator = TranslatorFactory::create('es', resourceProviders: [new BackboneDispatcherTranslationResourceProvider()]);
            $this->assertSame('Los datos del inspector en caché deben ser un arreglo.', $exception->trans($translator));
        }
        $this->assertSame('invalid cached data', $pool->getItem($key)->get());
    }

    public function testCachedDocumentationPreservesLinksExceptionsAndOperationMetadata(): void
    {
        $service = new DocumentedService();
        $real = new CountingInspector();
        $pool = LocalCacheFactory::pool(LocalCacheBackend::Memory, 'documented_inspector');
        $cached = new CachedInspector($real, $pool);

        $doc = $cached->getClassDoc($service);
        $methods = $cached->getPublicMethods($service);

        $this->assertSame([
            ['url' => 'https://example.com/service', 'description' => 'Service documentation.'],
            ['url' => 'https://example.com/reference', 'description' => null],
        ], $doc['links']);
        $this->assertSame('RuntimeException', $methods['double']['throws'][0]['type']);
        $this->assertNotEmpty($methods['double']['links']);
        $this->assertNotNull($methods['double']['operation']);
        $this->assertNull($methods['double']['operation']['name']);
        $this->assertNull($methods['double']['operation']['description']);
        $this->assertSame($doc, $cached->getClassDoc($service));
        $this->assertSame($methods, $cached->getPublicMethods($service));
        $this->assertSame(1, $real->calls['getClassDoc']);
        $this->assertSame(1, $real->calls['getPublicMethods']);
    }

    /**
     * @return iterable<string, array{string, string, list<array-key>, mixed, bool}>
     */
    public static function invalidCachedStructures(): iterable
    {
        $documentationCases = [
            'missing name' => [['name'], null, true],
            'non-string name' => [['name'], 12, false],
            'missing nullable summary' => [['summary'], null, true],
            'non-string summary' => [['summary'], [], false],
            'non-string description' => [['description'], false, false],
            'non-array tags' => [['tags'], 'invalid', false],
            'numeric tag name' => [['tags'], [0 => ['text']], false],
            'non-array tag entries' => [['tags'], ['author' => 'text'], false],
            'non-list tag entries' => [['tags'], ['author' => [2 => 'text']], false],
            'non-string tag entry' => [['tags'], ['author' => [null]], false],
            'non-array links' => [['links'], null, false],
            'non-list links' => [['links'], [2 => ['url' => 'https://example.com', 'description' => null]], false],
            'non-array link' => [['links'], ['invalid'], false],
            'missing link URL' => [['links'], [['description' => null]], false],
            'non-string link URL' => [['links'], [['url' => 12, 'description' => null]], false],
            'missing link description' => [['links'], [['url' => 'https://example.com']], false],
        ];
        foreach (self::cachedMethods() as [$method, $prefix]) {
            foreach ($documentationCases as $name => [$path, $replacement, $remove]) {
                yield $method . ': ' . $name => [$method, $prefix, $method === 'getClassDoc' ? $path : ['sum', ...$path], $replacement, $remove];
            }
        }
        yield 'nonexistent class name' => ['getClassDoc', 'backbone_dispatcher.class_doc', ['name'], 'MissingCachedInspectorService', false];
        $methodCases = [
            'numeric method key' => [[0], [], false],
            'non-array method' => [['sum'], 'invalid', false],
            'non-array parameters' => [['sum', 'parameters'], null, false],
            'non-list parameters' => [['sum', 'parameters'], ['named' => []], false],
            'non-array parameter' => [['sum', 'parameters', 0], 'invalid', false],
            'missing parameter name' => [['sum', 'parameters', 0, 'name'], null, true],
            'non-string parameter name' => [['sum', 'parameters', 0, 'name'], 12, false],
            'non-string parameter type' => [['sum', 'parameters', 0, 'type'], [], false],
            'non-boolean parameter requirement' => [['sum', 'parameters', 0, 'required'], 1, false],
            'missing parameter default' => [['sum', 'parameters', 0, 'default'], null, true],
            'non-string parameter description' => [['sum', 'parameters', 0, 'description'], [], false],
            'non-array return' => [['sum', 'returns'], 'int', false],
            'non-string return type' => [['sum', 'returns', 'type'], [], false],
            'missing return type' => [['sum', 'returns', 'type'], null, true],
            'non-string return description' => [['sum', 'returns', 'description'], [], false],
            'non-array throws' => [['sum', 'throws'], null, false],
            'non-array exception' => [['sum', 'throws'], ['invalid'], false],
            'invalid exception type' => [['sum', 'throws'], [['type' => false, 'description' => null]], false],
            'missing deprecation flag' => [['sum', 'deprecated'], null, true],
            'non-boolean deprecation flag' => [['sum', 'deprecated'], 1, false],
            'missing operation' => [['sum', 'operation'], null, true],
            'non-array operation' => [['sum', 'operation'], false, false],
            'invalid operation name' => [['sum', 'operation', 'name'], 12, false],
            'invalid operation description' => [['sum', 'operation', 'description'], [], false],
            'non-array operation parameters' => [['sum', 'operation', 'parameters'], false, false],
            'non-array operation results' => [['sum', 'operation', 'results'], 'invalid', false],
        ];
        foreach ($methodCases as $name => [$path, $replacement, $remove]) {
            yield $name => ['getPublicMethods', 'backbone_dispatcher.public_methods', $path, $replacement, $remove];
        }
    }

    /** @param list<array-key> $path */
    #[DataProvider('invalidCachedStructures')]
    public function testInvalidCachedStructuresAreRejectedWithoutRecomputingOrReplacingThem(
        string $method,
        string $prefix,
        array $path,
        mixed $replacement,
        bool $remove
    ): void {
        $real = new CountingInspector();
        $value = (new Inspector())->$method($this->worker);
        $this->assertIsArray($value);
        $invalid = $this->changeCachedField($value, $path, $replacement, $remove);
        $pool = LocalCacheFactory::pool(LocalCacheBackend::Memory, 'invalid_inspector_structure');
        $key = (new CacheKey())->build($prefix, ExampleWorker::class);
        $pool->save($pool->getItem($key)->set($invalid));
        $cached = new CachedInspector($real, $pool);

        try {
            $cached->$method($this->worker);
            $this->fail('Invalid cached structures must be rejected.');
        } catch (LogicException $exception) {
            $this->assertSame('Cached inspector data has an invalid structure.', $exception->getMessage());
            $translator = TranslatorFactory::create('es', resourceProviders: [new BackboneDispatcherTranslationResourceProvider()]);
            $this->assertSame('Los datos del inspector en caché tienen una estructura inválida.', $exception->trans($translator));
        }
        $this->assertSame(0, $real->calls[$method]);
        $this->assertSame($invalid, $pool->getItem($key)->get());
    }

    /**
     * @param array<array-key, mixed> $value
     * @param list<array-key> $path
     * @return array<array-key, mixed>
     */
    private function changeCachedField(array $value, array $path, mixed $replacement, bool $remove): array
    {
        $key = array_shift($path);
        $this->assertNotNull($key);
        if ($path !== []) {
            $child = $value[$key];
            $this->assertIsArray($child);
            $value[$key] = $this->changeCachedField($child, $path, $replacement, $remove);
        } elseif ($remove) {
            unset($value[$key]);
        } else {
            $value[$key] = $replacement;
        }

        return $value;
    }
}
