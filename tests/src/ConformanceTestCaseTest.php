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

use Derafu\BackboneDispatcher\Testing\DispatcherConformanceTestCase;
use Derafu\TestsBackboneDispatcher\Fixture\Harness\BadConformance;
use Derafu\TestsBackboneDispatcher\Fixture\Harness\BrokenExplorerConformance;
use Derafu\TestsBackboneDispatcher\Fixture\Harness\ExcludingConformance;
use Derafu\TestsBackboneDispatcher\Fixture\Harness\StrictNamesConformance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesNamespace;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;

#[CoversClass(DispatcherConformanceTestCase::class)]
#[UsesNamespace('Derafu\BackboneDispatcher')]
class ConformanceTestCaseTest extends TestCase
{
    private const BAD = 'example_package.example_component.bad_worker::';

    /**
     * Runs a check of a conformance case and gives the message of its failure.
     *
     * @param class-string<DispatcherConformanceTestCase> $class
     */
    private function failureOf(string $class, string $check): string
    {
        try {
            (new $class($check))->$check();
        } catch (ExpectationFailedException $e) {
            return $e->getMessage();
        }

        $this->fail(sprintf('The check %s of %s had to fail.', $check, $class));
    }

    /**
     * @param list<string> $lines
     */
    private function assertViolations(string $rule, array $lines, string $message): void
    {
        $this->assertStringContainsString(
            sprintf("The rule \"%s\" has violations:\n%s", $rule, implode("\n", $lines)),
            $message
        );
    }

    public function testFindsTheNamesThatDoNotFollowTheConvention(): void
    {
        $pattern = '/^[a-z][a-z0-9]*(_[a-z0-9]+)*$/';

        $this->assertViolations('names_follow_the_convention', [
            self::BAD . 'plainOperation: the operation name "plainOperation" does not match ' . $pattern . '.',
            self::BAD . 'plainOperation: the parameter "someText" does not match ' . $pattern . '.',
        ], $this->failureOf(BadConformance::class, 'testNamesFollowTheConvention'));
    }

    public function testTheNamePatternIsCheckedForEveryLevel(): void
    {
        $message = $this->failureOf(StrictNamesConformance::class, 'testNamesFollowTheConvention');

        $this->assertStringContainsString('example_package: the package name "example_package" does not match /^[a-z]+$/.', $message);
        $this->assertStringContainsString('example_package.example_component: the component name "example_component" does not match', $message);
        $this->assertStringContainsString('example_package.example_component.harness_worker: the worker name "harness_worker" does not match', $message);
        $this->assertStringContainsString('harness_worker::say_hello: the operation name "say_hello" does not match', $message);
    }

    public function testFindsTheOperationsWithoutDescription(): void
    {
        $this->assertViolations('operations_are_documented', [
            self::BAD . 'plainOperation: it has no description.',
            self::BAD . 'object_example: it has no description.',
            self::BAD . 'intersection: it has no description.',
        ], $this->failureOf(BadConformance::class, 'testOperationsAreDocumented'));
    }

    public function testFindsTheParametersWithoutExample(): void
    {
        $this->assertViolations('parameters_have_examples', [
            self::BAD . 'plainOperation: the parameter "document" has no example.',
            self::BAD . 'plainOperation: the parameter "someText" has no example.',
        ], $this->failureOf(BadConformance::class, 'testParametersHaveExamples'));
    }

    public function testFindsTheExamplesThatAClientCannotSendAsJson(): void
    {
        $this->assertViolations('examples_are_encodable_as_json', [
            self::BAD . 'object_example: the example of the parameter "payload" has an object of stdClass at payload.items.1.',
        ], $this->failureOf(BadConformance::class, 'testExamplesAreEncodableAsJson'));
    }

    public function testFindsTheParametersThatNoDeserializerBuilds(): void
    {
        $this->assertViolations('object_parameters_have_a_deserializer', [
            self::BAD . 'plainOperation: the parameter "document" is of type Derafu\TestsBackboneDispatcher\Fixture\Harness\HarnessDocument, which no deserializer builds.',
        ], $this->failureOf(BadConformance::class, 'testObjectParametersHaveADeserializer'));
    }

    public function testFindsTheReturnTypesThatAreNotSerializable(): void
    {
        $this->assertViolations('return_types_are_serializable', [
            self::BAD . 'plainOperation: the return type stdClass is not serializable: stdClass is not JsonSerializable and has no toArray().',
            self::BAD . 'object_example: the return type mixed is not serializable: mixed cannot be known to be serializable.',
            self::BAD . 'intersection: the return type Stringable&Countable is not serializable: Stringable&Countable is not JsonSerializable and has no toArray().',
        ], $this->failureOf(BadConformance::class, 'testReturnTypesAreSerializable'));
    }

    public function testADispatcherThatFollowsTheRulesPassesEveryCheck(): void
    {
        $case = new HarnessConformanceTest('test');

        $case->testNamesFollowTheConvention();
        $case->testOperationsAreDocumented();
        $case->testParametersHaveExamples();
        $case->testExamplesAreEncodableAsJson();
        $case->testObjectParametersHaveADeserializer();
        $case->testReturnTypesAreSerializable();

        $this->addToAssertionCount(1);
    }

    public function testTheExclusionsAllowTheViolationsThatTheyListWithTheirReason(): void
    {
        $case = new ExcludingConformance('test');

        $case->testNamesFollowTheConvention();
        $case->testOperationsAreDocumented();
        $case->testParametersHaveExamples();
        $case->testExamplesAreEncodableAsJson();
        $case->testObjectParametersHaveADeserializer();
        $case->testReturnTypesAreSerializable();

        $this->addToAssertionCount(1);
    }

    public function testAnExclusionThatHasNoViolationIsAFailure(): void
    {
        $case = new ExcludingConformance('testParametersHaveExamples');
        $case->withStaleExclusion = true;

        try {
            $case->testParametersHaveExamples();
            $this->fail('The stale exclusion had to fail.');
        } catch (ExpectationFailedException $e) {
            $this->assertStringContainsString(
                self::BAD . 'object_example: the exclusion is stale, it has no violation (It has an example, so this is stale.).',
                $e->getMessage()
            );
        }
    }

    public function testAnExplorerThatFailsIsAFailure(): void
    {
        $this->assertStringContainsString(
            'The explorer could not give the tree of the dispatcher',
            $this->failureOf(BrokenExplorerConformance::class, 'testNamesFollowTheConvention')
        );
    }
}
