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
use Derafu\BackboneDispatcher\Service\Reflection\Inspector;
use Derafu\BackboneDispatcher\Translation\BackboneDispatcherTranslationResourceProvider;
use Derafu\TestsBackboneDispatcher\Fixture\DocumentedService;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleBag;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleWorker;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleWorkerSubclass;
use Derafu\TestsBackboneDispatcher\Fixture\InheritedInspectionService;
use Derafu\TestsBackboneDispatcher\Fixture\LazyServiceProxy;
use Derafu\TestsBackboneDispatcher\Fixture\OrphanInspectionService;
use Derafu\TestsBackboneDispatcher\Fixture\StandaloneLazyService;
use Derafu\TestsBackboneDispatcher\Fixture\TypeInspectionService;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;
use Derafu\Translation\TranslatorFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use SplFixedArray;

/**
 * Covers `isOperation()`, `hasOperationAttribute()` and
 * `getOperationParameters()` — the narrow, PHPDoc-free methods the dispatch
 * path relies on. The rest
 * of `Inspector` (documentation reading) is exercised indirectly through
 * the dispatcher integration tests for now.
 */
#[CoversClass(Inspector::class)]
#[UsesClass(BackboneDispatcherTranslationResourceProvider::class)]
class InspectorTest extends TestCase
{
    private Inspector $inspector;

    private ExampleWorker $worker;

    protected function setUp(): void
    {
        $this->inspector = new Inspector();
        $this->worker = new ExampleWorker();
    }

    public function testIsOperationIsTrueForAPublicMethodDeclaredOnTheClass(): void
    {
        $this->assertTrue($this->inspector->isOperation($this->worker, 'sum'));
        $this->assertTrue($this->inspector->isOperation($this->worker, 'describeBag'));
    }

    public function testIsOperationIsFalseForANonexistentMethod(): void
    {
        $this->assertFalse($this->inspector->isOperation($this->worker, 'doesNotExist'));
    }

    public function testIsOperationIsFalseForAnUnderscorePrefixedName(): void
    {
        $this->assertFalse($this->inspector->isOperation($this->worker, '_hidden'));
    }

    public function testHasOperationAttributeIsTrueOnlyForATaggedOperation(): void
    {
        $this->assertTrue($this->inspector->hasOperationAttribute($this->worker, 'sum'));
        $this->assertFalse($this->inspector->hasOperationAttribute($this->worker, 'describeBag'));
    }

    public function testHasOperationAttributeIsFalseForANonexistentMethod(): void
    {
        $this->assertFalse($this->inspector->hasOperationAttribute($this->worker, 'doesNotExist'));
    }

    /**
     * `sum()` is declared on `ExampleWorker`, not on `ExampleWorkerSubclass`
     * — it must still count as an operation of the subclass: whether a
     * method is "an operation" is a fact about the method (public, tagged),
     * never about which class in the hierarchy happens to declare it.
     */
    public function testIsOperationIsTrueForAnInheritedMethodDeclaredOnAParentClass(): void
    {
        $subclass = new ExampleWorkerSubclass();

        $this->assertTrue($this->inspector->isOperation($subclass, 'sum'));
    }

    public function testHasOperationAttributeIsTrueForAnInheritedTaggedMethod(): void
    {
        $subclass = new ExampleWorkerSubclass();

        $this->assertTrue($this->inspector->hasOperationAttribute($subclass, 'sum'));
    }

    public function testGetPublicMethodsIncludesBothInheritedAndOwnTaggedMethods(): void
    {
        $subclass = new ExampleWorkerSubclass();

        $methods = $this->inspector->getPublicMethods($subclass);

        $this->assertArrayHasKey('sum', $methods);
        $this->assertArrayHasKey('multiply', $methods);
    }

    /**
     * `getTaggedOperations()`/`getPublicMethods()`'s `$filters['operation']`
     * were `Inspector`'s own, hardcoded reimplementation of exactly the rule
     * `TaggedOperationPolicy` already embodies (whether a method carries
     * `#[Operation]`) — a second, policy-independent way to decide "what
     * counts as an operation", reachable straight off `Inspector` without
     * ever going through an `OperationPolicyInterface`. That contradicts the
     * same principle the inheritance fix above enforces: only a Policy may
     * draw that line. A consumer who wants "the tagged operations of a
     * worker" must get there the same way `Explorer` does — enumerate with
     * `getPublicMethods($service)` (no filtering) and ask a Policy
     * (`TaggedOperationPolicy`, via `hasOperationAttribute()`) about each
     * candidate — never a shortcut baked into `Inspector` itself.
     */
    public function testGetPublicMethodsNeverFiltersByTheOperationAttributeItself(): void
    {
        $this->assertFalse(
            method_exists(Inspector::class, 'getTaggedOperations'),
        );

        $reflection = new ReflectionMethod(Inspector::class, 'getPublicMethods');

        $this->assertCount(
            1,
            $reflection->getParameters(),
            'getPublicMethods() must take only $service — no $filters bag through '
                . 'which Inspector could decide, on its own, what counts as an operation.'
        );
    }

    public function testGetOperationParametersResolvesRequiredAndOptionalParameters(): void
    {
        $parameters = $this->inspector->getOperationParameters($this->worker, 'sum');

        $this->assertSame('a', $parameters[0]['name']);
        $this->assertSame('int', $parameters[0]['type']);
        $this->assertTrue($parameters[0]['required']);

        $this->assertSame('b', $parameters[1]['name']);
        $this->assertFalse($parameters[1]['required']);
        $this->assertSame(10, $parameters[1]['default']);
    }

    public function testGetOperationParametersNeverParsesPhpDoc(): void
    {
        // sum() has no @param description in its docblock's own text, but
        // this asserts the contract, not an implementation detail: the
        // description is always null here, regardless of what the docblock
        // says, because getOperationParameters() never looks at it.
        $parameters = $this->inspector->getOperationParameters($this->worker, 'sum');

        $this->assertNull($parameters[0]['description']);
    }

    public function testOperationAttributeNameAndDescriptionOverridePhpDoc(): void
    {
        $methods = $this->inspector->getPublicMethods($this->worker);

        $this->assertSame('Sum', $methods['sum']['summary']);
        $this->assertSame(
            'Adds two integers together.',
            $methods['sum']['description']
        );
    }

    public function testOperationAttributeParameterOverridesApplyOnTopOfReflection(): void
    {
        $methods = $this->inspector->getPublicMethods($this->worker);
        $parameters = $methods['sum']['parameters'];

        // 'a' only overrides 'example' — 'type'/'required' stay reflected.
        $this->assertSame('int', $parameters[0]['type']);
        $this->assertTrue($parameters[0]['required']);
        $this->assertTrue(array_key_exists('example', $parameters[0]));
        $this->assertSame(5, $parameters[0]['example']);

        // 'b' only overrides 'description' — no 'example' key is added.
        $this->assertSame(
            'The addend, defaults to 10.',
            $parameters[1]['description']
        );
        $this->assertArrayNotHasKey('example', $parameters[1]);
    }

    public function testOperationWithoutOverridesKeepsReflectedNameAndDescription(): void
    {
        $methods = $this->inspector->getPublicMethods($this->worker);

        // describeBag() has no #[Operation] attribute at all, so nothing
        // here is overridden — summary/description come straight from
        // PHPDoc, parameters have no 'example' key.
        $this->assertNull($methods['describeBag']['operation']);
        $this->assertArrayNotHasKey('example', $methods['describeBag']['parameters'][0]);
    }

    /** @return array<string, array{object, string, string, string}> */
    public static function invalidOverrides(): array
    {
        return [
            'override is not an array' => [new class () {
                #[Operation(parameters: ['name' => 'bad'])]
                public function run(string $name): void
                {
                }
            }, 'name', 'array', 'string'],
            'type is not a string' => [new class () {
                #[Operation(parameters: ['name' => ['type' => []]])]
                public function run(string $name): void
                {
                }
            }, 'name.type', 'string', 'array'],
            'description is not a string' => [new class () {
                #[Operation(parameters: ['name' => ['description' => false]])]
                public function run(string $name): void
                {
                }
            }, 'name.description', 'string|null', 'bool'],
        ];
    }

    #[DataProvider('invalidOverrides')]
    public function testMalformedOverridesHaveATranslatedConfigurationError(object $worker, string $name, string $expected, string $type): void
    {
        try {
            $this->inspector->getPublicMethods($worker);
            $this->fail('Malformed parameter overrides must be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Invalid parameter override ' . $name . ': expected ' . $expected . '; ' . $type . ' given.', $exception->getMessage());
            $translator = TranslatorFactory::create('es', resourceProviders: [new BackboneDispatcherTranslationResourceProvider()]);
            $this->assertSame('Sobrescritura del parámetro ' . $name . ' inválida: se esperaba ' . $expected . '; se recibió ' . $type . '.', $exception->trans($translator));
        }
    }

    public function testOptionalOverridesAndArbitraryExamplesRemainAccepted(): void
    {
        $worker = new class () {
            #[Operation(parameters: ['name' => null])]
            public function unchanged(string $name): void
            {
            }

            #[Operation(parameters: ['name' => ['type' => 'string', 'description' => null, 'example' => ['nested' => [1, false, null]]]])]
            public function documented(string $name): void
            {
            }
        };
        $methods = $this->inspector->getPublicMethods($worker);
        $this->assertSame('string', $methods['unchanged']['parameters'][0]['type']);
        $parameter = $methods['documented']['parameters'][0];
        $this->assertSame('string', $parameter['type']);
        $this->assertNull($parameter['description']);
        $this->assertTrue(array_key_exists('example', $parameter));
        $this->assertSame(['nested' => [1, false, null]], $parameter['example']);
    }

    /** @return array<string, array{class-string<StandaloneLazyService>}> */
    public static function lazyServices(): array
    {
        return [
            'without a parent' => [StandaloneLazyService::class],
            'proxy with a parent' => [LazyServiceProxy::class],
        ];
    }

    /** @param class-string<StandaloneLazyService> $class */
    #[DataProvider('lazyServices')]
    public function testLazyServicesCanBeInspectedWithoutInitialization(string $class): void
    {
        $service = new $class();

        $doc = $this->inspector->getClassDoc($service);
        $this->assertSame(StandaloneLazyService::class, $doc['name']);
        $this->assertSame('A lazy service without a parent class.', $doc['summary']);
        $this->assertTrue($this->inspector->isOperation($service, 'describe'));
        $this->assertTrue($this->inspector->hasOperationAttribute($service, 'describe'));
        $parameters = $this->inspector->getOperationParameters($service, 'describe');
        $this->assertSame('name', $parameters[0]['name']);
        $this->assertSame('string', $parameters[0]['type']);
        $methods = $this->inspector->getPublicMethods($service);
        $this->assertArrayHasKey('describe', $methods);
        $this->assertFalse($service->isLazyObjectInitialized());
    }

    /** @return array<string, array{string, string}> */
    public static function reflectedTypeNames(): array
    {
        return [
            'intersection' => ['intersection', 'Countable&Stringable'],
            'union containing an intersection' => ['union', '(Countable&Stringable)|int'],
            'nullable named type keeps its existing representation' => ['nullable', 'string'],
        ];
    }

    #[DataProvider('reflectedTypeNames')]
    public function testParameterAndReturnTypesPreserveTheirReflectedStructure(string $method, string $expected): void
    {
        $service = new TypeInspectionService();
        $parameters = $this->inspector->getOperationParameters($service, $method);
        $methods = $this->inspector->getPublicMethods($service);

        $this->assertSame($expected, $parameters[0]['type']);
        $this->assertSame($expected, $methods[$method]['parameters'][0]['type']);
        $this->assertSame($expected, $methods[$method]['returns']['type']);
    }

    public function testTagsWithoutDescriptionsKeepTheirExistingEmptyRepresentations(): void
    {
        $methods = $this->inspector->getPublicMethods(new TypeInspectionService());
        $method = $methods['withoutDescriptions'];

        $this->assertSame('string', $method['returns']['type']);
        $this->assertNull($method['returns']['description']);
        $this->assertSame([['type' => 'RuntimeException', 'description' => null]], $method['throws']);
        $this->assertSame('', $method['parameters'][0]['description']);
    }

    public function testArrayOperationParametersResolveDocumentedElementTypes(): void
    {
        foreach (['describeBags', 'describeBagsGenericSyntax'] as $method) {
            $parameters = $this->inspector->getOperationParameters($this->worker, $method);
            $this->assertSame('bags', $parameters[0]['name']);
            $this->assertSame(ExampleBag::class . '[]', $parameters[0]['type']);
            $this->assertTrue($parameters[0]['required']);
        }
    }

    public function testClassAndMethodLinksPreserveDescriptionsAndUndescribedUrls(): void
    {
        $service = new DocumentedService();
        $doc = $this->inspector->getClassDoc($service);
        $methods = $this->inspector->getPublicMethods($service);

        $this->assertSame([
            ['url' => 'https://example.com/service', 'description' => 'Service documentation.'],
            ['url' => 'https://example.com/reference', 'description' => null],
        ], $doc['links']);
        $this->assertArrayNotHasKey('link', $doc['tags']);
        $this->assertSame([
            ['url' => 'https://example.com/double', 'description' => 'Operation documentation.'],
        ], $methods['double']['links']);
        $this->assertArrayNotHasKey('link', $methods['double']['tags']);
    }

    public function testAClassWithoutDocumentationHasNoInventedSummaryOrDescription(): void
    {
        $doc = $this->inspector->getClassDoc(new TypeInspectionService());

        $this->assertSame(TypeInspectionService::class, $doc['name']);
        $this->assertNull($doc['summary']);
        $this->assertNull($doc['description']);
        $this->assertSame([], $doc['tags']);
        $this->assertSame([], $doc['links']);
    }

    public function testClassAndArrayParameterDocumentationCanBeInheritedFromAnInterface(): void
    {
        $service = new InheritedInspectionService();
        $doc = $this->inspector->getClassDoc($service);
        $parameters = $this->inspector->getOperationParameters($service, 'inspect');
        $methods = $this->inspector->getPublicMethods($service);

        $this->assertSame('A documented inspection contract.', $doc['summary']);
        $this->assertSame('array', $parameters[0]['type']);
        $this->assertSame('Names to process.', $parameters[0]['description']);
        $this->assertSame('Processes the provided names.', $methods['inspect']['summary']);
    }

    public function testAnInheritDocWithoutAnAncestorDoesNotInventDocumentation(): void
    {
        $service = new OrphanInspectionService();
        $doc = $this->inspector->getClassDoc($service);
        $methods = $this->inspector->getPublicMethods($service);

        $this->assertNull($doc['summary']);
        $this->assertNull($doc['description']);
        $this->assertNull($methods['inspect']['summary']);
        $this->assertNull($methods['inspect']['description']);
        $this->assertSame('mixed', $methods['untyped']['parameters'][0]['type']);
        $this->assertSame('mixed', $methods['untyped']['returns']['type']);
        $this->assertSame('array', $methods['shapedArray']['parameters'][0]['type']);
    }

    public function testNativeArrayParametersWithoutPhpDocKeepTheirNativeType(): void
    {
        $parameters = $this->inspector->getOperationParameters(new SplFixedArray(), 'fromArray');

        $this->assertSame('array', $parameters[0]['type']);
        $this->assertTrue($parameters[0]['required']);
        $this->assertNull($parameters[0]['description']);
    }

    public function testAMalformedInlineLinkDoesNotDiscardOtherExceptionDocumentation(): void
    {
        $methods = $this->inspector->getPublicMethods(new OrphanInspectionService());

        $this->assertSame([
            ['type' => 'LogicException', 'description' => 'When the operation cannot continue.'],
        ], $methods['malformedLink']['throws']);
        $this->assertSame('Reports valid exceptions even when another tag has a malformed link.', $methods['malformedLink']['summary']);
    }
}
