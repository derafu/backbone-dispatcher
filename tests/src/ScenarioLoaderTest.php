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
use Derafu\BackboneDispatcher\Testing\Scenario\Expectation;
use Derafu\BackboneDispatcher\Testing\Scenario\ExpectationKind;
use Derafu\BackboneDispatcher\Testing\Scenario\Failure;
use Derafu\BackboneDispatcher\Testing\Scenario\Scenario;
use Derafu\BackboneDispatcher\Testing\Scenario\ScenarioLoader;
use Derafu\BackboneDispatcher\Testing\Scenario\Step;
use Derafu\BackboneDispatcher\Testing\Scenario\StepKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Tag\TaggedValue;

#[CoversClass(ScenarioLoader::class)]
#[UsesClass(Scenario::class)]
#[UsesClass(Step::class)]
#[UsesClass(Expectation::class)]
#[UsesClass(Failure::class)]
#[UsesClass(InvalidScenarioException::class)]
class ScenarioLoaderTest extends TestCase
{
    private const DISPATCH = <<<'YAML'
        description: A scenario.
        steps:
          first:
            dispatch:
              operation: p.c.w::run
        YAML;

    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            unlink($file);
        }
        $this->files = [];
    }

    private function write(string $yaml): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'scenario');
        file_put_contents($path, $yaml);
        $this->files[] = $path;

        return $path;
    }

    private function load(string $yaml): Scenario
    {
        return (new ScenarioLoader())->load($this->write($yaml));
    }

    public function testLoadsADispatchStepWithoutExpectations(): void
    {
        $scenario = $this->load(self::DISPATCH);

        $this->assertSame('A scenario.', $scenario->description);
        $this->assertSame([], $scenario->requires);
        $this->assertSame(['first'], array_keys($scenario->steps));
        $step = $scenario->steps['first'];
        $this->assertSame(StepKind::Dispatch, $step->kind);
        $this->assertSame('p.c.w::run', $step->target);
        $this->assertSame([], $step->input);
        $this->assertSame(ExpectationKind::None, $step->expectation->kind);
    }

    public function testLoadsTheScenarioFileThatItReceives(): void
    {
        $path = $this->write(self::DISPATCH);

        $this->assertSame($path, (new ScenarioLoader())->load($path)->path);
    }

    public function testLoadsTheRequirements(): void
    {
        $scenario = $this->load("requires: [docker, sii]\n" . self::DISPATCH);

        $this->assertSame(['docker', 'sii'], $scenario->requires);
    }

    public function testLoadsADeserializeStepWithTagsExpectationsAndCalls(): void
    {
        $scenario = $this->load(<<<'YAML'
            description: A scenario.
            steps:
              first:
                deserialize:
                  type: Vendor\Thing
                  data: !fixture {name: thing, args: {size: 3}}
                expect:
                  id: abc
                  'tags[0]': !any
                  call:
                    getName(): A name
                    getList().length: 2
                    getSelf(): !instanceof Vendor\Thing
              second:
                deserialize:
                  type: Vendor\Thing
                  data: !ref first.value.raw
                expect:
                  '@': !ref first.value
            YAML);

        $first = $scenario->steps['first'];
        $this->assertSame(StepKind::Deserialize, $first->kind);
        $this->assertSame('Vendor\Thing', $first->target);
        $this->assertInstanceOf(TaggedValue::class, $first->input);
        $this->assertSame(ExpectationKind::Expect, $first->expectation->kind);
        $this->assertSame(['id', 'tags[0]'], array_keys($first->expectation->expect));
        $this->assertSame(['getName()', 'getList().length', 'getSelf()'], array_keys($first->expectation->call));

        $second = $scenario->steps['second'];
        $this->assertInstanceOf(TaggedValue::class, $second->input);
        $this->assertSame(['@'], array_keys($second->expectation->expect));
    }

    public function testLoadsAFailureWithAListOfExceptions(): void
    {
        $scenario = $this->load(<<<'YAML'
            description: A scenario.
            steps:
              first:
                deserialize:
                  type: Vendor\Thing
                  data: text
                failure:
                  exception: [Vendor\One, Vendor\Two]
                  caused_by: Vendor\Cause
                  message: part of the message
            YAML);

        $expectation = $scenario->steps['first']->expectation;
        $this->assertSame(ExpectationKind::Failure, $expectation->kind);
        $this->assertNotNull($expectation->failure);
        $this->assertSame(['Vendor\One', 'Vendor\Two'], $expectation->failure->exceptions);
        $this->assertSame('Vendor\Cause', $expectation->failure->causedBy);
        $this->assertSame('part of the message', $expectation->failure->message);
    }

    public function testLoadsAFailureWithOneException(): void
    {
        $scenario = $this->load(<<<'YAML'
            description: A scenario.
            steps:
              first:
                dispatch:
                  operation: p.c.w::run
                failure:
                  exception: Vendor\One
            YAML);

        $failure = $scenario->steps['first']->expectation->failure;
        $this->assertNotNull($failure);
        $this->assertSame(['Vendor\One'], $failure->exceptions);
        $this->assertNull($failure->causedBy);
        $this->assertNull($failure->message);
    }

    public function testLoadsEither(): void
    {
        $scenario = $this->load(<<<'YAML'
            description: A scenario.
            steps:
              first:
                dispatch:
                  operation: p.c.w::run
                either:
                  expect:
                    track_id: !any
                  failure:
                    exception: Vendor\One
            YAML);

        $expectation = $scenario->steps['first']->expectation;
        $this->assertSame(ExpectationKind::Either, $expectation->kind);
        $this->assertSame(['track_id'], array_keys($expectation->expect));
        $this->assertNotNull($expectation->failure);
    }

    public function testAReferenceCanBeInsideTheArgumentsOfAFixtureAndInsideAList(): void
    {
        $scenario = $this->load(<<<'YAML'
            description: A scenario.
            steps:
              first:
                dispatch:
                  operation: p.c.w::run
              second:
                dispatch:
                  operation: p.c.w::run
                  parameters:
                    list: [1, !ref 'first.value.items[0].id']
                    thing: !fixture {name: thing, args: {id: !ref first.value.id}}
            YAML);

        $this->assertSame(['first', 'second'], array_keys($scenario->steps));
    }

    public function testLoadsTheScenariosOfTheTests(): void
    {
        $directory = dirname(__DIR__) . '/fixtures/scenarios';

        foreach (['dispatch/either', 'dispatch/failure', 'deserialize/round_trip', 'deserialize/failure'] as $name) {
            $scenario = (new ScenarioLoader())->load($directory . '/' . $name . '.yaml');

            $this->assertNotSame([], $scenario->steps, $name);
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidScenarios(): array
    {
        $step = "description: A.\nsteps:\n";

        return [
            'not yaml' => ["description: [\n", 'must be a valid YAML document'],
            'not a map' => ["- a\n- b\n", 'must be a map'],
            'unknown key' => ["description: A.\nsteps: {a: {dispatch: {operation: x}}}\nother: 1\n", 'The key other'],
            'no description' => ["steps: {a: {dispatch: {operation: x}}}\n", 'The key description'],
            'empty description' => ["description: ' '\nsteps: {a: {dispatch: {operation: x}}}\n", 'The key description'],
            'requires not a list' => ["description: A.\nrequires: docker\nsteps: {a: {dispatch: {operation: x}}}\n", 'The key requires'],
            'requires with a number' => ["description: A.\nrequires: [1]\nsteps: {a: {dispatch: {operation: x}}}\n", 'The key requires.0'],
            'no steps' => ["description: A.\n", 'The key steps'],
            'empty steps' => ["description: A.\nsteps: {}\n", 'The key steps'],
            'steps as a list' => ["description: A.\nsteps: [a]\n", 'The key steps'],
            'invalid step name' => [$step . "  Bad-Name:\n    dispatch: {operation: x}\n", 'The step name Bad-Name'],
            'numeric step name' => [$step . "  1:\n    dispatch: {operation: x}\n", 'The step name 1'],
            'step is not a map' => [$step . "  a: text\n", 'The key steps.a'],
            'step has an unknown key' => [$step . "  a:\n    dispatch: {operation: x}\n    other: 1\n", 'The key steps.a.other'],
            'step without a kind' => [$step . "  a:\n    expect: {x: 1}\n", 'must have exactly one of dispatch or deserialize'],
            'step with two kinds' => [$step . "  a:\n    dispatch: {operation: x}\n    deserialize: {type: T, data: x}\n", 'must have exactly one of dispatch or deserialize'],
            'two expectations' => [$step . "  a:\n    dispatch: {operation: x}\n    expect: {x: 1}\n    failure: {message: m}\n", 'can have only one of expect, failure or either'],
            'dispatch is not a map' => [$step . "  a:\n    dispatch: text\n", 'The key steps.a.dispatch'],
            'dispatch without operation' => [$step . "  a:\n    dispatch: {parameters: {}}\n", 'The key steps.a.dispatch.operation'],
            'dispatch with an unknown key' => [$step . "  a:\n    dispatch: {operation: x, other: 1}\n", 'The key steps.a.dispatch.other'],
            'parameters not a map' => [$step . "  a:\n    dispatch: {operation: x, parameters: [1]}\n", 'The key steps.a.dispatch.parameters'],
            'parameters with a numeric key' => [$step . "  a:\n    dispatch: {operation: x, parameters: {1: a}}\n", 'The key steps.a.dispatch.parameters'],
            'deserialize without type' => [$step . "  a:\n    deserialize: {data: x}\n", 'The key steps.a.deserialize.type'],
            'deserialize without data' => [$step . "  a:\n    deserialize: {type: T}\n", 'The key steps.a.deserialize.data'],
            'deserialize with data that is a number' => [$step . "  a:\n    deserialize: {type: T, data: 5}\n", 'The key steps.a.deserialize.data'],
            'deserialize with an unknown key' => [$step . "  a:\n    deserialize: {type: T, data: x, other: 1}\n", 'The key steps.a.deserialize.other'],
            'expect is empty' => [$step . "  a:\n    dispatch: {operation: x}\n    expect: {}\n", 'The key steps.a.expect'],
            'expect is not a map' => [$step . "  a:\n    dispatch: {operation: x}\n    expect: [1]\n", 'The key steps.a.expect'],
            'call in a dispatch step' => [$step . "  a:\n    dispatch: {operation: x}\n    expect: {call: {getA(): 1}}\n", 'The key steps.a.expect.call of the scenario'],
            'call is not a map' => [$step . "  a:\n    deserialize: {type: T, data: x}\n    expect: {call: [1]}\n", 'The key steps.a.expect.call'],
            'call with an invalid key' => [$step . "  a:\n    deserialize: {type: T, data: x}\n    expect: {call: {getA: 1}}\n", 'a method call like getName()'],
            'failure is empty' => [$step . "  a:\n    dispatch: {operation: x}\n    failure: {}\n", 'a map with exception, caused_by or message'],
            'failure with an unknown key' => [$step . "  a:\n    dispatch: {operation: x}\n    failure: {other: 1}\n", 'The key steps.a.failure.other'],
            'failure with an empty list of exceptions' => [$step . "  a:\n    dispatch: {operation: x}\n    failure: {exception: []}\n", 'The key steps.a.failure.exception'],
            'failure with an exception that is a map' => [$step . "  a:\n    dispatch: {operation: x}\n    failure: {exception: {a: b}}\n", 'The key steps.a.failure.exception'],
            'failure with an exception that is a number' => [$step . "  a:\n    dispatch: {operation: x}\n    failure: {exception: [1]}\n", 'The key steps.a.failure.exception.0'],
            'caused by in a dispatch step' => [$step . "  a:\n    dispatch: {operation: x}\n    failure: {caused_by: E}\n", 'only allowed in deserialize steps'],
            'failure with an empty message' => [$step . "  a:\n    dispatch: {operation: x}\n    failure: {message: ''}\n", 'The key steps.a.failure.message'],
            'either without failure' => [$step . "  a:\n    dispatch: {operation: x}\n    either: {expect: {x: 1}}\n", 'The key steps.a.either.failure'],
            'either without expect' => [$step . "  a:\n    dispatch: {operation: x}\n    either: {failure: {message: m}}\n", 'The key steps.a.either.expect'],
            'either with an unknown key' => [$step . "  a:\n    dispatch: {operation: x}\n    either: {expect: {x: 1}, failure: {message: m}, other: 1}\n", 'The key steps.a.either.other'],
            'either is not a map' => [$step . "  a:\n    dispatch: {operation: x}\n    either: text\n", 'The key steps.a.either'],
            'an unknown tag' => [$step . "  a:\n    dispatch: {operation: x, parameters: {a: !other b}}\n", 'The tag other is not allowed at steps.a.dispatch.parameters.a'],
            'any in the parameters' => [$step . "  a:\n    dispatch: {operation: x, parameters: {a: !any}}\n", 'The tag any is not allowed at steps.a.dispatch.parameters.a'],
            'instanceof outside of call' => [$step . "  a:\n    dispatch: {operation: x}\n    expect: {a: !instanceof T}\n", 'The tag instanceof is not allowed at steps.a.expect.a'],
            'fixture in an expectation' => [$step . "  a:\n    dispatch: {operation: x}\n    expect: {a: !fixture b}\n", 'The tag fixture is not allowed at steps.a.expect.a'],
            'any with a value' => [$step . "  a:\n    dispatch: {operation: x}\n    expect: {a: !any text}\n", 'a tag !any without a value'],
            'instanceof without a class' => [$step . "  a:\n    deserialize: {type: T, data: x}\n    expect: {call: {getA(): !instanceof}}\n", 'The key steps.a.expect.call.getA() of the scenario'],
            'a reference to an unknown step' => [$step . "  a:\n    dispatch: {operation: x, parameters: {a: !ref other.value}}\n", 'does not point to a step that comes before'],
            'a reference to the same step' => [$step . "  a:\n    dispatch: {operation: x}\n    expect: {a: !ref a.value}\n", 'does not point to a step that comes before'],
            'a reference that does not start with a step name' => [$step . "  a:\n    dispatch: {operation: x}\n  b:\n    dispatch: {operation: x, parameters: {a: !ref '[0].value'}}\n", 'does not point to a step that comes before'],
            'a reference to a later step' => [$step . "  a:\n    dispatch: {operation: x, parameters: {a: !ref b.value}}\n  b:\n    dispatch: {operation: x}\n", 'does not point to a step that comes before'],
            'an empty reference' => [$step . "  a:\n    dispatch: {operation: x, parameters: {a: !ref ''}}\n", 'The key steps.a.dispatch.parameters.a'],
            'a fixture with a list' => [$step . "  a:\n    dispatch: {operation: x, parameters: {a: !fixture [b]}}\n", 'a fixture name or a map with name and args'],
            'a fixture without a name' => [$step . "  a:\n    dispatch: {operation: x, parameters: {a: !fixture {args: {}}}}\n", 'The key steps.a.dispatch.parameters.a.name'],
            'a fixture with an unknown key' => [$step . "  a:\n    dispatch: {operation: x, parameters: {a: !fixture {name: b, other: 1}}}\n", 'The key steps.a.dispatch.parameters.a.other'],
            'a fixture with arguments that are not a map' => [$step . "  a:\n    dispatch: {operation: x, parameters: {a: !fixture {name: b, args: [1]}}}\n", 'The key steps.a.dispatch.parameters.a.args'],
            'a tag inside the arguments of a fixture' => [$step . "  a:\n    dispatch: {operation: x, parameters: {a: !fixture {name: b, args: {c: !any}}}}\n", 'The tag any is not allowed at steps.a.dispatch.parameters.a.args.c'],
        ];
    }

    #[DataProvider('invalidScenarios')]
    public function testRejectsAnInvalidScenario(string $yaml, string $message): void
    {
        $this->expectException(InvalidScenarioException::class);
        $this->expectExceptionMessage($message);

        $this->load($yaml);
    }
}
