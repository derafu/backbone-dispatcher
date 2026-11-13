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

use Derafu\BackboneDispatcher\Service\Serialization\Serializer;
use Derafu\BackboneDispatcher\Testing\Fixture\FixtureRegistry;
use Derafu\BackboneDispatcher\Testing\Scenario\Evaluated;
use Derafu\BackboneDispatcher\Testing\Scenario\Expectation;
use Derafu\BackboneDispatcher\Testing\Scenario\ExpectationChecker;
use Derafu\BackboneDispatcher\Testing\Scenario\ExpectationKind;
use Derafu\BackboneDispatcher\Testing\Scenario\Failure;
use Derafu\BackboneDispatcher\Testing\Scenario\NotEvaluable;
use Derafu\BackboneDispatcher\Testing\Scenario\Step;
use Derafu\BackboneDispatcher\Testing\Scenario\StepKind;
use Derafu\BackboneDispatcher\Testing\Scenario\StepResult;
use Derafu\BackboneDispatcher\Testing\Scenario\TagResolver;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleBag;
use Derafu\TestsBackboneDispatcher\Fixture\Harness\HarnessDocument;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesNamespace;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\Yaml\Tag\TaggedValue;

#[CoversClass(ExpectationChecker::class)]
#[CoversClass(Evaluated::class)]
#[CoversClass(NotEvaluable::class)]
#[UsesClass(TagResolver::class)]
#[UsesClass(FixtureRegistry::class)]
#[UsesClass(Step::class)]
#[UsesClass(StepResult::class)]
#[UsesClass(Expectation::class)]
#[UsesClass(Failure::class)]
#[UsesClass(Serializer::class)]
#[UsesNamespace('Derafu\BackboneDispatcher\Testing\Exception')]
class ExpectationCheckerTest extends TestCase
{
    private ExpectationChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new ExpectationChecker(new TagResolver(new FixtureRegistry()), new Serializer());
    }

    /**
     * @param array<string, mixed> $expect
     * @param array<string, mixed> $call
     */
    private function expecting(array $expect, array $call = [], StepKind $kind = StepKind::Dispatch, string $target = 'p.c.w::run'): Step
    {
        return new Step('first', $kind, $target, [], new Expectation(ExpectationKind::Expect, $expect, $call));
    }

    private function failing(?Failure $failure, StepKind $kind = StepKind::Dispatch): Step
    {
        return new Step('first', $kind, 'p.c.w::run', [], new Expectation(ExpectationKind::Failure, failure: $failure));
    }

    private function ok(mixed $value, ?object $object = null): StepResult
    {
        return new StepResult('first', true, $value, $object);
    }

    private function problem(string $title = 'Vendor\Problem', string $detail = 'Something failed.'): StepResult
    {
        return new StepResult('first', false, problem: ['type' => 'about:blank', 'title' => $title, 'detail' => $detail]);
    }

    private function threw(RuntimeException $e): StepResult
    {
        return new StepResult('first', false, throwable: $e);
    }

    /**
     * @param array<string, array{value: mixed, problem: array{type: string, title: string, detail: string}|null}> $references
     * @return list<string>
     */
    private function check(Step $step, StepResult $result, array $references = []): array
    {
        return $this->checker->check($step, $result, $references);
    }

    public function testAStepWithoutExpectationsOnlyHasToSucceed(): void
    {
        $step = new Step('first', StepKind::Dispatch, 'p.c.w::run', [], new Expectation());

        $this->assertSame([], $this->check($step, $this->ok(['any' => 'thing'])));
        $this->assertSame(
            ['Step first failed and it had to succeed: Vendor\Problem: Something failed.'],
            $this->check($step, $this->problem())
        );
        $this->assertSame(
            ['Step first failed and it had to succeed: RuntimeException: It threw.'],
            $this->check($step, $this->threw(new RuntimeException('It threw.')))
        );
    }

    public function testComparesTheValuesOfJmesPathExpressionsWithStrictEquality(): void
    {
        $value = ['id' => 'abc', 'count' => 2, 'tags' => ['a', 'b'], 'nested' => ['ok' => true]];
        $step = $this->expecting([
            'id' => 'abc',
            'count' => 2,
            'tags[0]' => 'a',
            'length(tags)' => 2,
            'nested.ok' => true,
            'tags' => ['a', 'b'],
        ]);

        $this->assertSame([], $this->check($step, $this->ok($value)));
    }

    public function testReportsEachDifferenceWithTheExpressionAndTheValues(): void
    {
        $step = $this->expecting(['id' => 'abc', 'count' => '2', 'missing' => 'x']);

        $this->assertSame([
            'Step first: count: expected "2", got 2.',
            'Step first: missing: expected "x", got null.',
        ], $this->check($step, $this->ok(['id' => 'abc', 'count' => 2])));
    }

    public function testTheWholeValueIsTheExpressionAt(): void
    {
        $this->assertSame([], $this->check($this->expecting(['@' => 'Low']), $this->ok('Low')));
        $this->assertSame([], $this->check($this->expecting(['@' => [1, 2]]), $this->ok([1, 2])));
        $this->assertSame(
            ['Step first: @: expected "High", got "Low".'],
            $this->check($this->expecting(['@' => 'High']), $this->ok('Low'))
        );
    }

    public function testAnExpressionOverAValueThatIsNotAnArrayCannotBeEvaluated(): void
    {
        $this->assertSame(
            ['Step first: the value is string, an array is needed to evaluate "id".'],
            $this->check($this->expecting(['id' => 'abc']), $this->ok('text'))
        );
    }

    public function testAnyMatchesAnythingThatIsNotNull(): void
    {
        $any = new TaggedValue('any', '');
        $step = $this->expecting(['a' => $any, 'b' => $any, 'c' => $any, 'd' => $any]);

        $this->assertSame(
            ['Step first: d: expected !any, got null.'],
            $this->check($step, $this->ok(['a' => 0, 'b' => '', 'c' => false]))
        );
    }

    public function testAReferenceIsComparedWithTheValueItPointsTo(): void
    {
        $references = ['before' => ['value' => ['id' => 'abc'], 'problem' => null]];
        $step = $this->expecting(['id' => new TaggedValue('ref', 'before.value.id'), '@' => new TaggedValue('ref', 'before.value')]);

        $this->assertSame([], $this->check($step, $this->ok(['id' => 'abc']), $references));
        $this->assertSame(
            [
                'Step first: id: expected !ref before.value.id, got "other".',
                'Step first: @: expected !ref before.value, got {"id":"other"}.',
            ],
            $this->check($step, $this->ok(['id' => 'other']), $references)
        );
    }

    public function testTheTagsCanBeInsideAMapOrAList(): void
    {
        $any = new TaggedValue('any', '');
        $step = $this->expecting(['@' => ['id' => $any, 'items' => [1, $any]]]);

        $this->assertSame([], $this->check($step, $this->ok(['id' => 'x', 'items' => [1, 'y']])));
        $this->assertCount(1, $this->check($step, $this->ok(['id' => null, 'items' => [1, 'y']])));
        $this->assertCount(1, $this->check($step, $this->ok(['id' => 'x', 'items' => [1, null]])));
    }

    public function testAMapHasToHaveTheSameKeysInTheSameOrder(): void
    {
        $step = $this->expecting(['@' => ['a' => 1, 'b' => 2]]);

        $this->assertSame([], $this->check($step, $this->ok(['a' => 1, 'b' => 2])));
        $this->assertCount(1, $this->check($step, $this->ok(['b' => 2, 'a' => 1])));
        $this->assertCount(1, $this->check($step, $this->ok(['a' => 1])));
        $this->assertCount(1, $this->check($step, $this->ok(['a' => 1, 'b' => 2, 'c' => 3])));
        $this->assertCount(1, $this->check($step, $this->ok('a')));
    }

    public function testShowsALongValueTruncated(): void
    {
        $long = str_repeat('x', 300);
        $differences = $this->check($this->expecting(['a' => 'short']), $this->ok(['a' => $long]));

        $this->assertSame(['Step first: a: expected "short", got "' . str_repeat('x', 199) . '....'], $differences);
    }

    public function testShowsAnObjectByItsClassAndAFloatAsPhpWritesIt(): void
    {
        $step = $this->expecting(['@' => 'x']);

        $this->assertSame(['Step first: @: expected "x", got stdClass.'], $this->check($step, $this->ok(new stdClass())));
        $this->assertSame(['Step first: @: expected "x", got NAN.'], $this->check($step, $this->ok(NAN)));
        $this->assertSame(['Step first: @: expected "x", got 1.5.'], $this->check($step, $this->ok(1.5)));
    }

    public function testADeserializeStepHasToBuildAnInstanceOfItsType(): void
    {
        $step = $this->expecting([], [], StepKind::Deserialize, HarnessDocument::class);

        $this->assertSame([], $this->check($step, $this->ok(['id' => 'a'], new HarnessDocument('a', 'doc:a'))));
        $this->assertSame(
            ['Step first built Derafu\TestsBackboneDispatcher\Fixture\ExampleBag, expected an instance of Derafu\TestsBackboneDispatcher\Fixture\Harness\HarnessDocument.'],
            $this->check($step, $this->ok(['name' => 'a'], new ExampleBag('a', 1)))
        );
        $this->assertSame(
            ['Step first built null, expected an instance of Derafu\TestsBackboneDispatcher\Fixture\Harness\HarnessDocument.'],
            $this->check($step, $this->ok(null))
        );
    }

    public function testAUnionTypeAcceptsAnyOfItsCandidatesAndAnArrayCandidateAcceptsAnArray(): void
    {
        $step = $this->expecting([], [], StepKind::Deserialize, '?' . HarnessDocument::class . '|array');

        $this->assertSame([], $this->check($step, $this->ok(['id' => 'a'], new HarnessDocument('a', 'doc:a'))));
        $this->assertSame([], $this->check($step, $this->ok(['some' => 'data'])));
        $this->assertCount(1, $this->check($step, $this->ok('text')));
    }

    public function testAnArrayIsNotEnoughWhenTheTypeHasNoArrayCandidate(): void
    {
        $step = $this->expecting([], [], StepKind::Deserialize, HarnessDocument::class);

        $this->assertCount(1, $this->check($step, $this->ok(['id' => 'a'])));
    }

    public function testCallsAMethodOfTheObjectAndComparesWhatItReturns(): void
    {
        $document = new HarnessDocument('abc', 'doc:abc');
        $step = $this->expecting([], ['getId()' => 'abc'], StepKind::Deserialize, HarnessDocument::class);

        $this->assertSame([], $this->check($step, $this->ok([], $document)));
        $this->assertSame(
            ['Step first: getId(): expected "other", got "abc".'],
            $this->check($this->expecting([], ['getId()' => 'other'], StepKind::Deserialize, HarnessDocument::class), $this->ok([], $document))
        );
    }

    public function testCallsAMethodAndEvaluatesAnExpressionOverWhatItReturns(): void
    {
        $document = new HarnessDocument('abc', 'doc:abc');
        $step = $this->expecting([], ['toArray().length' => 7, 'toArray().raw' => new TaggedValue('any', '')], StepKind::Deserialize, HarnessDocument::class);

        $this->assertSame([], $this->check($step, $this->ok([], $document)));
        $this->assertSame(
            ['Step first: toArray().length: expected 8, got 7.'],
            $this->check($this->expecting([], ['toArray().length' => 8], StepKind::Deserialize, HarnessDocument::class), $this->ok([], $document))
        );
    }

    public function testAnExpressionOverWhatAMethodReturnsNeedsAnArray(): void
    {
        $document = new HarnessDocument('abc', 'doc:abc');
        $step = $this->expecting([], ['getId().length' => 3], StepKind::Deserialize, HarnessDocument::class);

        $this->assertSame(
            ['Step first: getId().length: the value is string, an array is needed to evaluate "length".'],
            $this->check($step, $this->ok([], $document))
        );
    }

    public function testComparesAnObjectThatAMethodReturnsByItsSerialization(): void
    {
        $holder = new class (new ExampleBag('a', 1)) {
            public function __construct(private readonly ExampleBag $bag)
            {
            }

            public function getBag(): ExampleBag
            {
                return $this->bag;
            }
        };
        $step = $this->expecting([], ['getBag()' => ['name' => 'a', 'amount' => 1]], StepKind::Deserialize, $holder::class);

        $this->assertSame([], $this->check($step, $this->ok([], $holder)));
    }

    public function testInstanceofChecksTheClassOfWhatAMethodReturns(): void
    {
        $holder = new class (new ExampleBag('a', 1)) {
            public function __construct(private readonly ExampleBag $bag)
            {
            }

            public function getBag(): ExampleBag
            {
                return $this->bag;
            }
        };
        $matches = $this->expecting([], ['getBag()' => new TaggedValue('instanceof', ExampleBag::class)], StepKind::Deserialize, $holder::class);
        $differs = $this->expecting([], ['getBag()' => new TaggedValue('instanceof', HarnessDocument::class)], StepKind::Deserialize, $holder::class);

        $this->assertSame([], $this->check($matches, $this->ok([], $holder)));
        $this->assertSame(
            ['Step first: getBag(): expected !instanceof Derafu\TestsBackboneDispatcher\Fixture\Harness\HarnessDocument, got Derafu\TestsBackboneDispatcher\Fixture\ExampleBag.'],
            $this->check($differs, $this->ok([], $holder))
        );
    }

    public function testInstanceofDoesNotMatchAScalar(): void
    {
        $document = new HarnessDocument('abc', 'doc:abc');
        $step = $this->expecting([], ['getId()' => new TaggedValue('instanceof', HarnessDocument::class)], StepKind::Deserialize, HarnessDocument::class);

        $this->assertCount(1, $this->check($step, $this->ok([], $document)));
    }

    public function testAMethodThatDoesNotExistIsADifference(): void
    {
        $document = new HarnessDocument('abc', 'doc:abc');
        $step = $this->expecting([], ['getOther()' => 1], StepKind::Deserialize, HarnessDocument::class);

        $this->assertSame(
            ['Step first: getOther(): Derafu\TestsBackboneDispatcher\Fixture\Harness\HarnessDocument has no public method getOther().'],
            $this->check($step, $this->ok([], $document))
        );
    }

    public function testACallNeedsAnObject(): void
    {
        $step = $this->expecting([], ['getId()' => 'abc'], StepKind::Deserialize, HarnessDocument::class);

        $this->assertSame(
            ['Step first: getId(): there is no object to call it on.', 'Step first built array, expected an instance of Derafu\TestsBackboneDispatcher\Fixture\Harness\HarnessDocument.'],
            array_reverse($this->check($step, $this->ok([])))
        );
    }

    public function testAStepThatSucceededHasToFailWhenFailureIsExpected(): void
    {
        $this->assertSame(
            ['Step first succeeded and it had to fail.'],
            $this->check($this->failing(new Failure(['Vendor\Problem'])), $this->ok(['a' => 1]))
        );
    }

    public function testAFailureWithoutDetailsOnlyHasToFail(): void
    {
        $this->assertSame([], $this->check($this->failing(null), $this->problem()));
    }

    public function testTheTitleOfTheProblemHasToBeOneOfTheExceptionsOrASubclass(): void
    {
        $step = $this->failing(new Failure([LogicException::class, 'Vendor\Other']));

        $this->assertSame([], $this->check($step, $this->problem('Vendor\Other')));
        $this->assertSame([], $this->check($step, $this->problem(LogicException::class)));
        $this->assertSame([], $this->check($step, $this->problem(\DomainException::class)));
        $this->assertSame(
            ['Step first failed with Vendor\Problem, expected LogicException or Vendor\Other.'],
            $this->check($step, $this->problem('Vendor\Problem'))
        );
    }

    public function testTheMessageOfTheProblemHasToContainTheText(): void
    {
        $step = $this->failing(new Failure(message: 'went wrong'));

        $this->assertSame([], $this->check($step, $this->problem(detail: 'Something went wrong here.')));
        $this->assertSame(
            ['Step first failed with the message "Something else.", expected one that contains "went wrong".'],
            $this->check($step, $this->problem(detail: 'Something else.'))
        );
    }

    public function testTheExceptionOfADeserializeStepHasToBeOneOfTheExceptionsOrASubclass(): void
    {
        $step = $this->failing(new Failure([RuntimeException::class]), StepKind::Deserialize);

        $this->assertSame([], $this->check($step, $this->threw(new RuntimeException('It threw.'))));
        $this->assertSame(
            ['Step first failed with LogicException, expected RuntimeException.'],
            $this->check($step, new StepResult('first', false, throwable: new LogicException('It threw.')))
        );
    }

    public function testTheMessageOfTheExceptionHasToContainTheText(): void
    {
        $step = $this->failing(new Failure(message: 'It threw'), StepKind::Deserialize);

        $this->assertSame([], $this->check($step, $this->threw(new RuntimeException('It threw badly.'))));
        $this->assertCount(1, $this->check($step, $this->threw(new RuntimeException('Other.'))));
    }

    public function testTheCauseHasToBeInTheChainOfPreviousExceptions(): void
    {
        $step = $this->failing(new Failure(causedBy: LogicException::class), StepKind::Deserialize);
        $chained = new RuntimeException('Outer.', 0, new RuntimeException('Middle.', 0, new LogicException('Cause.')));

        $this->assertSame([], $this->check($step, $this->threw($chained)));
        $this->assertSame([], $this->check($step, new StepResult('first', false, throwable: new LogicException('Itself.'))));
        $this->assertSame(
            ['Step first failed but LogicException is not in its chain of exceptions.'],
            $this->check($step, $this->threw(new RuntimeException('Alone.')))
        );
        $this->assertCount(1, $this->check($step, $this->problem()));
    }

    public function testEveryDifferenceOfAFailureIsReported(): void
    {
        $step = $this->failing(new Failure([LogicException::class], LogicException::class, 'cause text'), StepKind::Deserialize);

        $this->assertCount(3, $this->check($step, $this->threw(new RuntimeException('Alone.'))));
    }

    public function testEitherAcceptsASuccessThatMatchesOrAFailureThatMatches(): void
    {
        $step = new Step('first', StepKind::Dispatch, 'p.c.w::run', [], new Expectation(
            ExpectationKind::Either,
            ['id' => 'abc'],
            failure: new Failure(['Vendor\Problem'])
        ));

        $this->assertSame([], $this->check($step, $this->ok(['id' => 'abc'])));
        $this->assertSame([], $this->check($step, $this->problem('Vendor\Problem')));
        $this->assertSame(['Step first: id: expected "abc", got "other".'], $this->check($step, $this->ok(['id' => 'other'])));
        $this->assertSame(['Step first failed with Vendor\Other, expected Vendor\Problem.'], $this->check($step, $this->problem('Vendor\Other')));
    }
}
