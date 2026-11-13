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

use Derafu\BackboneDispatcher\Testing\Exception\InvalidScenarioException;
use Derafu\BackboneDispatcher\Testing\ScenarioTestCase;
use Derafu\TestsBackboneDispatcher\Fixture\Harness\EmptyScenarios;
use Derafu\TestsBackboneDispatcher\Fixture\Harness\FailingScenarios;
use Derafu\TestsBackboneDispatcher\Fixture\Harness\ShutdownScenarios;
use Derafu\TestsBackboneDispatcher\Fixture\Harness\SkippingScenarios;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesNamespace;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\SkippedWithMessageException;
use PHPUnit\Framework\TestCase;

#[CoversClass(ScenarioTestCase::class)]
#[UsesNamespace('Derafu\BackboneDispatcher')]
class ScenarioTestCaseTest extends TestCase
{
    private const DIRECTORY = __DIR__ . '/../fixtures';

    public function testTheProviderGivesTheScenariosOfTheDirectoryInOrder(): void
    {
        $scenarios = iterator_to_array(HarnessScenarioTest::scenarioProvider());

        $this->assertSame([
            'deserialize/failure.yaml',
            'deserialize/round_trip.yaml',
            'deserialize/union_and_array.yaml',
            'dispatch/either.yaml',
            'dispatch/expect_jmespath.yaml',
            'dispatch/failure.yaml',
            'dispatch/fixtures_and_references.yaml',
            'dispatch/round_trip.yaml',
        ], array_keys($scenarios));
        $this->assertSame([realpath(self::DIRECTORY) . '/scenarios/deserialize/failure.yaml'], [realpath($scenarios['deserialize/failure.yaml'][0])]);
    }

    public function testRunsEveryScenarioOfTheDirectoryAgainstTheRealServices(): void
    {
        HarnessScenarioTest::setUpBeforeClass();

        try {
            foreach (HarnessScenarioTest::scenarioProvider() as [$path]) {
                (new HarnessScenarioTest('testScenario'))->testScenario($path);
            }
        } finally {
            HarnessScenarioTest::tearDownAfterClass();
        }

        $this->addToAssertionCount(1);
    }

    public function testADirectoryWithoutScenariosIsAnError(): void
    {
        $this->expectException(InvalidScenarioException::class);
        $this->expectExceptionMessage('has no scenarios');

        iterator_to_array(EmptyScenarios::scenarioProvider());
    }

    public function testASkippedScenarioSaysWhy(): void
    {
        SkippingScenarios::setUpBeforeClass();

        try {
            $this->expectException(SkippedWithMessageException::class);
            $this->expectExceptionMessage('Docker is not available.');

            (new SkippingScenarios('testScenario'))->testScenario(self::DIRECTORY . '/scenarios_skipped/needs_docker.yaml');
        } finally {
            SkippingScenarios::tearDownAfterClass();
        }
    }

    public function testAScenarioWhoseFixtureIsNotAvailableIsSkippedToo(): void
    {
        SkippingScenarios::setUpBeforeClass();

        try {
            $this->expectException(SkippedWithMessageException::class);
            $this->expectExceptionMessage('The requirement docker is not available: Docker is not available.');

            (new SkippingScenarios('testScenario'))->testScenario(self::DIRECTORY . '/scenarios_skipped/fixture_needs_docker.yaml');
        } finally {
            SkippingScenarios::tearDownAfterClass();
        }
    }

    public function testAWrongExpectationFailsTheTestAndNamesTheScenarioTheStepAndTheValues(): void
    {
        FailingScenarios::setUpBeforeClass();

        try {
            $this->expectException(ExpectationFailedException::class);
            $this->expectExceptionMessage('A scenario whose expectation is wrong');
            $this->expectExceptionMessage('Step greet: message: expected "wrong", got "Hello, Ana!".');

            (new FailingScenarios('testScenario'))->testScenario(self::DIRECTORY . '/scenarios_failing/wrong_value.yaml');
        } finally {
            FailingScenarios::tearDownAfterClass();
        }
    }

    public function testTheShutdownHooksOfTheFixturesAreCalledAfterTheLastTest(): void
    {
        ShutdownScenarios::reset();

        ShutdownScenarios::setUpBeforeClass();
        $beforeTheLastTest = ShutdownScenarios::shutdowns();

        ShutdownScenarios::tearDownAfterClass();
        $afterTheLastTest = ShutdownScenarios::shutdowns();

        ShutdownScenarios::tearDownAfterClass();
        $afterTearingDownTwice = ShutdownScenarios::shutdowns();

        $this->assertSame([0, 1, 1], [$beforeTheLastTest, $afterTheLastTest, $afterTearingDownTwice]);
    }
}
