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

use Derafu\BackboneDispatcher\Testing\Exception\FixtureUnavailableException;
use Derafu\BackboneDispatcher\Testing\Exception\InvalidScenarioException;
use Derafu\BackboneDispatcher\Testing\Fixture\FixtureRegistry;
use Derafu\BackboneDispatcher\Testing\Scenario\TagResolver;
use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Tag\TaggedValue;

#[CoversClass(TagResolver::class)]
#[UsesClass(FixtureRegistry::class)]
#[UsesClass(FixtureUnavailableException::class)]
#[UsesClass(InvalidScenarioException::class)]
class TagResolverTest extends TestCase
{
    private TagResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new TagResolver(
            (new FixtureRegistry())
                ->fixture('document', static fn (): string => 'doc:abc')
                ->fixture('person', static fn (array $arguments): array => ['name' => $arguments['name'] ?? 'Ana'])
                ->requirement('docker', static fn (): string => 'No docker.')
                ->fixture('server', static fn (): string => 'up', ['docker'])
        );
    }

    public function testValuesWithoutTagsAreReturnedAsTheyAre(): void
    {
        $value = ['a' => 1, 'b' => ['c', null, true], 'd' => 'text'];

        $this->assertSame($value, $this->resolver->resolve($value, []));
        $this->assertSame('text', $this->resolver->resolve('text', []));
    }

    public function testResolvesAFixtureByName(): void
    {
        $this->assertSame('doc:abc', $this->resolver->resolve(new TaggedValue('fixture', 'document'), []));
    }

    public function testResolvesAFixtureWithArguments(): void
    {
        $value = new TaggedValue('fixture', ['name' => 'person', 'args' => ['name' => 'Bea']]);

        $this->assertSame(['name' => 'Bea'], $this->resolver->resolve($value, []));
    }

    public function testResolvesAFixtureWithoutArgumentsGivenAsAMap(): void
    {
        $this->assertSame(['name' => 'Ana'], $this->resolver->resolve(new TaggedValue('fixture', ['name' => 'person']), []));
    }

    public function testResolvesTheReferencesInsideTheArgumentsOfAFixture(): void
    {
        $references = ['first' => ['value' => ['who' => 'Cris'], 'problem' => null]];
        $value = new TaggedValue('fixture', ['name' => 'person', 'args' => ['name' => new TaggedValue('ref', 'first.value.who')]]);

        $this->assertSame(['name' => 'Cris'], $this->resolver->resolve($value, $references));
    }

    public function testResolvesTheTagsAtAnyDepth(): void
    {
        $references = ['first' => ['value' => ['id' => 7], 'problem' => null]];
        $value = ['list' => [1, new TaggedValue('ref', 'first.value.id')], 'doc' => new TaggedValue('fixture', 'document')];

        $this->assertSame(['list' => [1, 7], 'doc' => 'doc:abc'], $this->resolver->resolve($value, $references));
    }

    public function testAReferenceToANullValueIsAnInvalidScenario(): void
    {
        $this->expectException(InvalidScenarioException::class);
        $this->expectExceptionMessage('The reference first.value.id does not exist in the results of the previous steps.');

        $this->resolver->resolve(new TaggedValue('ref', 'first.value.id'), ['first' => ['value' => ['id' => null], 'problem' => null]]);
    }

    public function testAReferenceIsAJmesPathExpression(): void
    {
        $references = ['first' => ['value' => ['items' => [['id' => 7], ['id' => 8]]], 'problem' => null]];

        $this->assertSame(8, $this->resolver->resolve(new TaggedValue('ref', 'first.value.items[1].id'), $references));
        $this->assertSame([7, 8], $this->resolver->resolve(new TaggedValue('ref', 'first.value.items[*].id'), $references));
    }

    public function testAReferenceToAnEmptyListAndToAListWithOneElementGiveTheList(): void
    {
        $references = ['first' => ['value' => ['empty' => [], 'single' => ['a']], 'problem' => null]];

        $this->assertSame([], $this->resolver->resolve(new TaggedValue('ref', 'first.value.empty'), $references));
        $this->assertSame(['a'], $this->resolver->resolve(new TaggedValue('ref', 'first.value.single'), $references));
    }

    public function testAReferenceThatIsNotAValidExpressionIsAnInvalidScenarioThatKeepsTheCause(): void
    {
        try {
            $this->resolver->resolve(new TaggedValue('ref', 'first..value'), ['first' => ['value' => 1, 'problem' => null]]);
            $this->fail('The expression is not valid.');
        } catch (InvalidScenarioException $e) {
            $this->assertStringContainsString('The expression first..value is not a valid JMESPath expression', $e->getMessage());
            $this->assertInstanceOf(Exception::class, $e->getPrevious());
        }
    }

    public function testAReferenceToAValueThatTheResultsDoNotHaveIsAnInvalidScenario(): void
    {
        $this->expectException(InvalidScenarioException::class);
        $this->expectExceptionMessage('The reference first.value.id does not exist in the results of the previous steps.');

        $this->resolver->resolve(new TaggedValue('ref', 'first.value.id'), ['first' => ['value' => [], 'problem' => null]]);
    }

    public function testAnAssertionTagIsNotResolved(): void
    {
        $any = new TaggedValue('any', '');

        $this->assertSame($any, $this->resolver->resolve($any, []));
    }

    public function testAFixtureWithAMissingRequirementIsUnavailable(): void
    {
        $this->expectException(FixtureUnavailableException::class);

        $this->resolver->resolve(new TaggedValue('fixture', 'server'), []);
    }
}
