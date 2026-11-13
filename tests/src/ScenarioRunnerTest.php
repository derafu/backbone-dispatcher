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
use Derafu\BackboneDispatcher\Exception\NoDeserializerFoundException;
use Derafu\BackboneDispatcher\Service\Serialization\Serializer;
use Derafu\BackboneDispatcher\Testing\Exception\FixtureUnavailableException;
use Derafu\BackboneDispatcher\Testing\Fixture\FixtureRegistry;
use Derafu\BackboneDispatcher\Testing\Scenario\Expectation;
use Derafu\BackboneDispatcher\Testing\Scenario\ScenarioRunner;
use Derafu\BackboneDispatcher\Testing\Scenario\Step;
use Derafu\BackboneDispatcher\Testing\Scenario\StepKind;
use Derafu\BackboneDispatcher\Testing\Scenario\StepResult;
use Derafu\BackboneDispatcher\Testing\Scenario\TagResolver;
use Derafu\TestsBackboneDispatcher\Fixture\Harness\HarnessDocument;
use Derafu\TestsBackboneDispatcher\Fixture\Harness\HarnessServices;
use Derafu\Value\Exception\TypeMismatchException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesNamespace;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Yaml\Tag\TaggedValue;

#[CoversClass(ScenarioRunner::class)]
#[CoversClass(StepResult::class)]
#[UsesClass(TagResolver::class)]
#[UsesClass(FixtureRegistry::class)]
#[UsesClass(FixtureUnavailableException::class)]
#[UsesClass(Step::class)]
#[UsesClass(Expectation::class)]
#[UsesNamespace('Derafu\BackboneDispatcher')]
class ScenarioRunnerTest extends TestCase
{
    private const WORKER = 'example_package.example_component.harness_worker';

    private ScenarioRunner $runner;

    protected function setUp(): void
    {
        $this->runner = new ScenarioRunner(
            HarnessServices::dispatcher(),
            HarnessServices::objectFactory(),
            new Serializer(),
            new TagResolver(
                (new FixtureRegistry())
                    ->fixture('document', static fn (): string => 'doc:abc')
                    ->fixture('number', static fn (): int => 5)
                    ->requirement('docker', static fn (): string => 'No docker.')
                    ->fixture('server', static fn (): string => 'doc:up', ['docker'])
            ),
        );
    }

    private function dispatch(string $operation, mixed $parameters = []): Step
    {
        return new Step('first', StepKind::Dispatch, self::WORKER . '::' . $operation, $parameters, new Expectation());
    }

    private function deserialize(string $type, mixed $data): Step
    {
        return new Step('first', StepKind::Deserialize, $type, $data, new Expectation());
    }

    public function testAnOperationThatWorksGivesItsSerializedValue(): void
    {
        $result = $this->runner->run($this->dispatch('say_hello', ['name' => 'Ana']), []);

        $this->assertTrue($result->success);
        $this->assertSame('first', $result->name);
        $this->assertSame(['message' => 'Hello, Ana!', 'tags' => ['a', 'b']], $result->value);
        $this->assertNull($result->problem);
        $this->assertNull($result->throwable);
        $this->assertNull($result->object);
    }

    public function testAnOperationThatFailsGivesItsProblem(): void
    {
        $result = $this->runner->run($this->dispatch('fail'), []);

        $this->assertFalse($result->success);
        $this->assertNull($result->value);
        $this->assertNotNull($result->problem);
        $this->assertSame(RuntimeException::class, $result->problem['title']);
        $this->assertSame('Something went wrong in the harness.', $result->problem['detail']);
        $this->assertNotSame('', $result->problem['type']);
        $this->assertNull($result->throwable);
    }

    public function testTheParametersOfAnOperationCanHaveTags(): void
    {
        $step = $this->dispatch('echo_document', ['document' => new TaggedValue('fixture', 'document')]);

        $result = $this->runner->run($step, []);

        $this->assertTrue($result->success);
        $this->assertSame(['id' => 'abc', 'raw' => 'doc:abc', 'length' => 7], $result->value);
    }

    public function testTheParametersOfAnOperationCanReferToThePreviousSteps(): void
    {
        $references = ['before' => ['value' => ['who' => 'Bea'], 'problem' => null]];
        $step = $this->dispatch('say_hello', ['name' => new TaggedValue('ref', 'before.value.who')]);

        $result = $this->runner->run($step, $references);

        $this->assertIsArray($result->value);
        $this->assertSame('Hello, Bea!', $result->value['message']);
    }

    public function testBuildsAnObjectAndGivesItAndItsSerialization(): void
    {
        $result = $this->runner->run($this->deserialize(HarnessDocument::class, 'doc:abc'), []);

        $this->assertTrue($result->success);
        $this->assertInstanceOf(HarnessDocument::class, $result->object);
        $this->assertSame(['id' => 'abc', 'raw' => 'doc:abc', 'length' => 7], $result->value);
        $this->assertNull($result->throwable);
    }

    public function testTheDataOfAnObjectCanBeAFixture(): void
    {
        $result = $this->runner->run($this->deserialize(HarnessDocument::class, new TaggedValue('fixture', 'document')), []);

        $this->assertInstanceOf(HarnessDocument::class, $result->object);
    }

    public function testAnArrayThatTheObjectFactoryGivesHasNoObject(): void
    {
        $result = $this->runner->run($this->deserialize(HarnessDocument::class . '|array', ['some' => 'data']), []);

        $this->assertTrue($result->success);
        $this->assertNull($result->object);
        $this->assertSame(['some' => 'data'], $result->value);
    }

    public function testARejectionOfTheObjectFactoryIsTheFailureOfTheStep(): void
    {
        $result = $this->runner->run($this->deserialize(HarnessDocument::class, 'not a document'), []);

        $this->assertFalse($result->success);
        $this->assertInstanceOf(NoDeserializerFoundException::class, $result->throwable);
        $this->assertNull($result->problem);
        $this->assertNull($result->object);
    }

    public function testTheDataOfAnObjectThatIsNotATextOrAMapIsAnError(): void
    {
        $this->expectException(TypeMismatchException::class);

        $this->runner->run($this->deserialize(HarnessDocument::class, new TaggedValue('fixture', 'number')), []);
    }

    public function testAFixtureThatIsNotAvailableStopsTheStep(): void
    {
        $this->expectException(FixtureUnavailableException::class);

        $this->runner->run($this->deserialize(HarnessDocument::class, new TaggedValue('fixture', 'server')), []);
    }

    public function testAnInvalidOperationIdIsAnError(): void
    {
        $this->expectException(InvalidOperationIdException::class);

        $this->runner->run(new Step('first', StepKind::Dispatch, 'not valid', [], new Expectation()), []);
    }

    public function testTheReferenceOfAResultOnlyHasTheValueAndTheProblem(): void
    {
        $result = new StepResult('first', false, null, null, ['type' => 't', 'title' => 'T', 'detail' => 'D']);

        $this->assertSame(['value' => null, 'problem' => ['type' => 't', 'title' => 'T', 'detail' => 'D']], $result->toReference());
    }
}
