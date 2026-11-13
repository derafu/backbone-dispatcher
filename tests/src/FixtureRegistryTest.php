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
use Derafu\Value\ValueCaster;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(FixtureRegistry::class)]
#[UsesClass(FixtureUnavailableException::class)]
#[UsesClass(InvalidScenarioException::class)]
class FixtureRegistryTest extends TestCase
{
    public function testResolvesAFixtureWithoutArguments(): void
    {
        $registry = (new FixtureRegistry())->fixture('answer', static fn (array $arguments): int => 42);

        $this->assertSame(42, $registry->resolve('answer'));
    }

    public function testPassesTheArgumentsToTheProvider(): void
    {
        $registry = (new FixtureRegistry())
            ->fixture('greeting', static fn (array $arguments): string => 'Hello, ' . ValueCaster::requireString($arguments['name']))
        ;

        $this->assertSame('Hello, Ana', $registry->resolve('greeting', ['name' => 'Ana']));
    }

    public function testDoesNotCacheTheResultOfAProvider(): void
    {
        $calls = 0;
        $registry = (new FixtureRegistry())->fixture('counter', static function () use (&$calls): int {
            return ++$calls;
        });

        $this->assertSame(1, $registry->resolve('counter'));
        $this->assertSame(2, $registry->resolve('counter'));
    }

    public function testAnUnknownFixtureIsAnInvalidScenario(): void
    {
        $this->expectException(InvalidScenarioException::class);
        $this->expectExceptionMessage('The fixture missing is not registered.');

        (new FixtureRegistry())->resolve('missing');
    }

    public function testAFixtureWithAnAvailableRequirementIsResolved(): void
    {
        $registry = (new FixtureRegistry())
            ->requirement('docker', static fn (): ?string => null)
            ->fixture('server', static fn (): string => 'up', ['docker'])
        ;

        $this->assertSame('up', $registry->resolve('server'));
    }

    public function testAFixtureWithAMissingRequirementIsUnavailableWithItsReason(): void
    {
        $registry = (new FixtureRegistry())
            ->requirement('docker', static fn (): string => 'Docker is not running.')
            ->fixture('server', static fn (): string => 'up', ['docker'])
        ;

        $this->expectException(FixtureUnavailableException::class);
        $this->expectExceptionMessage('The requirement docker is not available: Docker is not running.');

        $registry->resolve('server');
    }

    public function testReportsTheReasonOfTheFirstMissingRequirement(): void
    {
        $registry = (new FixtureRegistry())
            ->requirement('one', static fn (): ?string => null)
            ->requirement('two', static fn (): string => 'Two is missing.')
            ->requirement('three', static fn (): string => 'Three is missing.')
        ;

        $this->assertNull($registry->unavailable());
        $this->assertNull($registry->unavailable('one'));
        $this->assertSame('Two is missing.', $registry->unavailable('one', 'two', 'three'));
    }

    public function testAnUnknownRequirementIsAnInvalidScenario(): void
    {
        $this->expectException(InvalidScenarioException::class);
        $this->expectExceptionMessage('The requirement missing is not registered.');

        (new FixtureRegistry())->unavailable('missing');
    }

    public function testChecksARequirementOnlyOnce(): void
    {
        $checks = 0;
        $registry = (new FixtureRegistry())->requirement('slow', static function () use (&$checks): string {
            ++$checks;

            return 'Not available.';
        });

        $registry->unavailable('slow');
        $registry->unavailable('slow');

        $this->assertSame(1, $checks);
    }

    public function testRegisteringARequirementAgainChecksItAgain(): void
    {
        $registry = (new FixtureRegistry())->requirement('slow', static fn (): string => 'Not available.');
        $this->assertSame('Not available.', $registry->unavailable('slow'));

        $registry->requirement('slow', static fn (): ?string => null);

        $this->assertNull($registry->unavailable('slow'));
    }

    public function testCallsTheShutdownHooksInReverseOrderOnlyOnce(): void
    {
        $calls = [];
        $registry = (new FixtureRegistry())
            ->onShutdown(static function () use (&$calls): void {
                $calls[] = 'first';
            })
            ->onShutdown(static function () use (&$calls): void {
                $calls[] = 'second';
            })
        ;

        $registry->shutdown();
        $registry->shutdown();

        $this->assertSame(['second', 'first'], $calls);
    }

    public function testCallsEveryShutdownHookAndThrowsTheFirstExceptionAtTheEnd(): void
    {
        $calls = [];
        $registry = (new FixtureRegistry())
            ->onShutdown(static function () use (&$calls): void {
                $calls[] = 'first';
            })
            ->onShutdown(static function (): void {
                throw new RuntimeException('Second failed.');
            })
            ->onShutdown(static function (): void {
                throw new RuntimeException('Third failed.');
            })
        ;

        try {
            $registry->shutdown();
            $this->fail('The shutdown must throw.');
        } catch (RuntimeException $e) {
            $this->assertSame('Third failed.', $e->getMessage());
        }

        $this->assertSame(['first'], $calls);
    }
}
