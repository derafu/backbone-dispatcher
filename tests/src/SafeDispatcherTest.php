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

use Derafu\BackboneDispatcher\Contract\OperationRequestInterface;
use Derafu\BackboneDispatcher\Contract\OperationResultInterface;
use Derafu\BackboneDispatcher\Contract\TypedDispatcherInterface;
use Derafu\BackboneDispatcher\Service\Deserialization\FromArrayDeserializer;
use Derafu\BackboneDispatcher\Service\Deserialization\ObjectFactoryRegistry;
use Derafu\BackboneDispatcher\Service\Dispatch\DirectDispatcher;
use Derafu\BackboneDispatcher\Service\Dispatch\SafeDispatcher;
use Derafu\BackboneDispatcher\Service\Dispatch\TypedDispatcher;
use Derafu\BackboneDispatcher\Service\Policy\AllowAllOperationPolicy;
use Derafu\BackboneDispatcher\Service\Reflection\Inspector;
use Derafu\BackboneDispatcher\Service\Resolution\Caster;
use Derafu\BackboneDispatcher\Service\Resolution\Resolver;
use Derafu\BackboneDispatcher\Service\Resolution\Validator;
use Derafu\BackboneDispatcher\Service\Serialization\Serializer;
use Derafu\BackboneDispatcher\Translation\BackboneDispatcherTranslationResourceProvider;
use Derafu\BackboneDispatcher\ValueObject\ExecutionMetadata;
use Derafu\BackboneDispatcher\ValueObject\OperationRequest;
use Derafu\BackboneDispatcher\ValueObject\OperationResult;
use Derafu\BackboneDispatcher\ValueObject\ProblemDetail;
use Derafu\BackboneDispatcher\ValueObject\SafeThrowable;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleComponent;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleGreeting;
use Derafu\TestsBackboneDispatcher\Fixture\ExamplePackage;
use Derafu\TestsBackboneDispatcher\Fixture\ExamplePackageRegistry;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleWorker;
use Derafu\TestsBackboneDispatcher\Fixture\MissingDataTypeResult;
use Derafu\TestsBackboneDispatcher\Fixture\SystemMetricFailures;
use Derafu\Translation\Exception\Core\TranslatableLogicException as LogicException;
use Derafu\Translation\TranslatorFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Integration test: real registry, real Resolver, real TypedDispatcher and
 * Serializer underneath — exercises the one behavior that only
 * `SafeDispatcher` has: it never throws, and it is the only tier that
 * flattens domain objects via the Serializer.
 */
#[CoversClass(SafeDispatcher::class)]
#[UsesClass(BackboneDispatcherTranslationResourceProvider::class)]
#[UsesClass(TypedDispatcher::class)]
#[UsesClass(DirectDispatcher::class)]
#[UsesClass(Resolver::class)]
#[UsesClass(Inspector::class)]
#[UsesClass(Caster::class)]
#[UsesClass(ObjectFactoryRegistry::class)]
#[UsesClass(FromArrayDeserializer::class)]
#[UsesClass(Validator::class)]
#[UsesClass(AllowAllOperationPolicy::class)]
#[UsesClass(Serializer::class)]
#[UsesClass(OperationRequest::class)]
#[UsesClass(OperationResult::class)]
#[UsesClass(ProblemDetail::class)]
#[UsesClass(SafeThrowable::class)]
#[UsesClass(ExecutionMetadata::class)]
class SafeDispatcherTest extends TestCase
{
    private SafeDispatcher $dispatcher;

    protected function setUp(): void
    {
        $worker = new ExampleWorker();
        $component = new ExampleComponent(['example_worker' => $worker]);
        $package = new ExamplePackage(['example_component' => $component]);

        $registry = new ExamplePackageRegistry();
        $registry->registerPackage('example_package', $package);

        $inspector = new Inspector();

        $directDispatcher = new DirectDispatcher(
            $registry,
            $inspector,
            new Resolver(
                $inspector,
                new Caster(new ObjectFactoryRegistry(fallback: new FromArrayDeserializer())),
                new Validator()
            ),
        );

        $this->dispatcher = new SafeDispatcher(
            new TypedDispatcher($directDispatcher),
            new Serializer(),
            environment: 'test',
            debug: true
        );
    }

    public function testWrapsAScalarResultAsASuccessfulOperationResult(): void
    {
        $request = new OperationRequest(
            'example_package',
            'example_component',
            'example_worker',
            'sum',
            ['a' => 5, 'b' => 7]
        );

        $result = $this->dispatcher->dispatch($request);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(12, $result->getValue());
        $this->assertNull($result->getProblem());
        $this->assertGreaterThanOrEqual(0.0, $result->getMetadata()->getRealTime());
        $this->assertSame(getmypid(), $result->getMetadata()->getPid());
        $this->assertSame('integer', $result->getDataType());
    }

    public function testFlattensADomainObjectReturnedByTheOperationUsingTheSerializer(): void
    {
        $request = new OperationRequest(
            'example_package',
            'example_component',
            'example_worker',
            'makeGreeting',
            ['name' => 'World']
        );

        $result = $this->dispatcher->dispatch($request);

        $this->assertTrue($result->isSuccess());
        $this->assertSame([
            'message' => 'Hello, World!',
            'reply' => [
                'message' => 'Hi there!',
                'reply' => null,
            ],
        ], $result->getValue());
        // The *pre-serialization* type, even though getValue() above is
        // already the flattened array — data_type is exactly the piece of
        // information that is otherwise lost once Serializer runs.
        $this->assertSame(ExampleGreeting::class, $result->getDataType());
    }

    public function testNeverThrowsAndReturnsAFailureOperationResultInstead(): void
    {
        $request = OperationRequest::fromId(
            'example_package.example_component.example_worker::fail'
        );

        $result = $this->dispatcher->dispatch($request);

        $this->assertFalse($result->isSuccess());
        $this->assertNull($result->getValue());

        $problem = $result->getProblem();
        $this->assertNotNull($problem);
        $this->assertSame(
            'Something went wrong while running the operation.',
            $problem->getDetail()
        );
        $this->assertSame(
            'example_package.example_component.example_worker::fail',
            $problem->getInstance()
        );
        $this->assertSame('RuntimeException', $problem->getThrowable()->getClass());
        $this->assertGreaterThanOrEqual(0.0, $result->getMetadata()->getRealTime());
        $this->assertNull($result->getDataType());
        // Same clock reading, not two independent `microtime(true)` calls.
        $this->assertSame($result->getMetadata()->getTimestamp(), $problem->getTimestamp());
    }

    public function testASuccessfulResultWithoutItsDataTypeBecomesAnExplicitFailure(): void
    {
        $request = new OperationRequest('example_package', 'example_component', 'example_worker', 'sum', ['a' => 5]);
        $validResult = $this->dispatcher->dispatch($request);
        $this->assertTrue($validResult->isSuccess());
        $invalidResult = new MissingDataTypeResult($validResult);
        $typed = new class ($invalidResult) implements TypedDispatcherInterface {
            public function __construct(private readonly OperationResultInterface $result)
            {
            }

            public function dispatch(OperationRequestInterface $request): OperationResultInterface
            {
                return $this->result;
            }
        };
        $dispatcher = new SafeDispatcher($typed, new Serializer(), environment: 'test', debug: true);

        $result = $dispatcher->dispatch($request);

        $this->assertFalse($result->isSuccess());
        $this->assertNull($result->getValue());
        $problem = $result->getProblem();
        $this->assertNotNull($problem);
        $this->assertSame(LogicException::class, $problem->getThrowable()->getClass());
        $this->assertSame('A successful operation result must specify its data type.', $problem->getDetail());
        $translator = TranslatorFactory::create('es', resourceProviders: [new BackboneDispatcherTranslationResourceProvider()]);
        $this->assertSame('El resultado de una operación exitosa debe especificar su tipo de dato.', (new LogicException($problem->getDetail()))->trans($translator));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testUnavailableMetricsDoNotTurnASuccessIntoAFailure(): void
    {
        require __DIR__ . '/Fixture/SystemMetricFailures.php';
        SystemMetricFailures::$cpu = true;
        SystemMetricFailures::$pid = true;
        SystemMetricFailures::$load = true;
        $request = new OperationRequest(
            'example_package',
            'example_component',
            'example_worker',
            'sum',
            ['a' => 5, 'b' => 7]
        );

        $result = $this->dispatcher->dispatch($request);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(12, $result->getValue());
        $this->assertSame('integer', $result->getDataType());
        $this->assertNull($result->getProblem());
        $this->assertUnavailableMetrics($result);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testUnavailableMetricsPreserveTheOriginalOperationFailure(): void
    {
        require __DIR__ . '/Fixture/SystemMetricFailures.php';
        SystemMetricFailures::$cpu = true;
        SystemMetricFailures::$pid = true;
        SystemMetricFailures::$load = true;
        $request = OperationRequest::fromId('example_package.example_component.example_worker::fail');

        $result = $this->dispatcher->dispatch($request);

        $this->assertFalse($result->isSuccess());
        $problem = $result->getProblem();
        $this->assertNotNull($problem);
        $this->assertSame('RuntimeException', $problem->getThrowable()->getClass());
        $this->assertSame('Something went wrong while running the operation.', $problem->getDetail());
        $this->assertSame($result->getMetadata()->getTimestamp(), $problem->getTimestamp());
        $this->assertUnavailableMetrics($result);
    }

    private function assertUnavailableMetrics(OperationResultInterface $result): void
    {
        $metadata = $result->getMetadata();
        $this->assertNull($metadata->getUserTime());
        $this->assertNull($metadata->getSystemTime());
        $this->assertNull($metadata->getPid());
        $this->assertNull($metadata->getLoadAverage1Min());
        $this->assertNull($metadata->getLoadAverage5Min());
        $this->assertNull($metadata->getLoadAverage15Min());
        $this->assertGreaterThanOrEqual(0.0, $metadata->getRealTime());
        $this->assertGreaterThan(0, $metadata->getPeakMemory());
    }
}
