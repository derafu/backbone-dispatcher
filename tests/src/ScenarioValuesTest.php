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

use Derafu\BackboneDispatcher\Testing\Scenario\Expectation;
use Derafu\BackboneDispatcher\Testing\Scenario\ExpectationKind;
use Derafu\BackboneDispatcher\Testing\Scenario\Failure;
use Derafu\BackboneDispatcher\Testing\Scenario\Scenario;
use Derafu\BackboneDispatcher\Testing\Scenario\Step;
use Derafu\BackboneDispatcher\Testing\Scenario\StepKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Scenario::class)]
#[CoversClass(Step::class)]
#[CoversClass(Expectation::class)]
#[CoversClass(Failure::class)]
class ScenarioValuesTest extends TestCase
{
    public function testAnExpectationWithoutArgumentsHasNothingToCompare(): void
    {
        $expectation = new Expectation();

        $this->assertSame(ExpectationKind::None, $expectation->kind);
        $this->assertSame([], $expectation->expect);
        $this->assertSame([], $expectation->call);
        $this->assertNull($expectation->failure);
    }

    public function testAFailureWithoutArgumentsChecksNothing(): void
    {
        $failure = new Failure();

        $this->assertSame([], $failure->exceptions);
        $this->assertNull($failure->causedBy);
        $this->assertNull($failure->message);
    }

    public function testAStepAndAScenarioKeepWhatTheyAreBuiltWith(): void
    {
        $expectation = new Expectation(ExpectationKind::Expect, ['id' => 'abc'], ['getId()' => 'abc']);
        $step = new Step('first', StepKind::Deserialize, 'Vendor\\Thing', 'text', $expectation);
        $scenario = new Scenario('a.yaml', 'A scenario.', ['docker'], ['first' => $step]);

        $this->assertSame('first', $step->name);
        $this->assertSame(StepKind::Deserialize, $step->kind);
        $this->assertSame('Vendor\\Thing', $step->target);
        $this->assertSame('text', $step->input);
        $this->assertSame($expectation, $step->expectation);
        $this->assertSame(['id' => 'abc'], $expectation->expect);
        $this->assertSame(['getId()' => 'abc'], $expectation->call);
        $this->assertSame('a.yaml', $scenario->path);
        $this->assertSame('A scenario.', $scenario->description);
        $this->assertSame(['docker'], $scenario->requires);
        $this->assertSame(['first' => $step], $scenario->steps);
    }
}
