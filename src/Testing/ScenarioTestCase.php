<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\BackboneDispatcher\Testing;

use Derafu\BackboneDispatcher\Contract\ObjectFactoryInterface;
use Derafu\BackboneDispatcher\Contract\SafeDispatcherInterface;
use Derafu\BackboneDispatcher\Contract\SerializerInterface;
use Derafu\BackboneDispatcher\Service\Serialization\Serializer;
use Derafu\BackboneDispatcher\Testing\Exception\FixtureUnavailableException;
use Derafu\BackboneDispatcher\Testing\Exception\InvalidScenarioException;
use Derafu\BackboneDispatcher\Testing\Fixture\FixtureRegistry;
use Derafu\BackboneDispatcher\Testing\Scenario\ExpectationChecker;
use Derafu\BackboneDispatcher\Testing\Scenario\ScenarioLoader;
use Derafu\BackboneDispatcher\Testing\Scenario\ScenarioRunner;
use Derafu\BackboneDispatcher\Testing\Scenario\TagResolver;
use FilesystemIterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Runs the YAML scenarios of a dispatcher against its real services.
 *
 * The test case that a dispatcher extends to test its business cases: every
 * `*.yaml` file of the scenarios directory is one test. A scenario is a list of
 * steps, each one dispatches an operation or builds an object with the object
 * factory, and says what it expects. The format is described in the page
 * "Testing" of the documentation of the package.
 *
 * The cases are the files of the directory, not this class: adding a case is
 * adding a file. A directory without scenarios is an error, not a test that
 * passes by running nothing.
 *
 * A subclass declares the coverage it wants (`#[CoversNamespace]`), as the
 * scenarios go through whatever the operations they dispatch go through.
 */
abstract class ScenarioTestCase extends TestCase
{
    /**
     * The registries of the fixtures, by test class.
     *
     * @var array<class-string, FixtureRegistry>
     */
    private static array $registries = [];

    /**
     * The directory searched, recursively, for the `*.yaml` scenarios.
     */
    abstract protected static function scenariosDirectory(): string;

    /**
     * The dispatcher that the `dispatch` steps use: the real one, the same
     * that the application boots.
     */
    abstract protected static function dispatcher(): SafeDispatcherInterface;

    /**
     * The object factory that the `deserialize` steps use: the real one.
     */
    abstract protected static function objectFactory(): ObjectFactoryInterface;

    /**
     * The serializer that gives the value of a `deserialize` step.
     */
    protected static function serializer(): SerializerInterface
    {
        return new Serializer();
    }

    /**
     * Registers the fixtures and the requirements of the scenarios.
     *
     * By default it registers nothing, which is enough for scenarios that only
     * have literal data.
     */
    protected static function fixtures(FixtureRegistry $registry): void
    {
    }

    /**
     * Builds the registry of the fixtures before the first test.
     *
     * A class that overrides this method has to call the parent.
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $registry = new FixtureRegistry();
        static::fixtures($registry);
        self::$registries[static::class] = $registry;
    }

    /**
     * Calls the shutdown hooks of the fixtures after the last test.
     *
     * A class that overrides this method has to call the parent.
     */
    public static function tearDownAfterClass(): void
    {
        $registry = self::$registries[static::class] ?? null;
        unset(self::$registries[static::class]);
        $registry?->shutdown();

        parent::tearDownAfterClass();
    }

    /**
     * The scenario files, by path relative to the directory.
     *
     * @return iterable<string, array{string}>
     * @throws InvalidScenarioException When there are no scenarios.
     */
    public static function scenarioProvider(): iterable
    {
        $directory = static::scenariosDirectory();

        $found = [];
        if (is_dir($directory)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($files as $file) {
                if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'yaml') {
                    $found[substr($file->getPathname(), strlen($directory) + 1)] = $file->getPathname();
                }
            }
        }

        if ($found === []) {
            throw InvalidScenarioException::forEmptyDirectory($directory);
        }

        ksort($found);

        foreach ($found as $relative => $path) {
            yield $relative => [$path];
        }
    }

    #[DataProvider('scenarioProvider')]
    public function testScenario(string $path): void
    {
        $registry = self::$registries[static::class];
        $scenario = (new ScenarioLoader())->load($path);

        $reason = $registry->unavailable(...$scenario->requires);
        if ($reason !== null) {
            $this->markTestSkipped($reason);
        }

        $serializer = static::serializer();
        $tags = new TagResolver($registry);
        $runner = new ScenarioRunner(static::dispatcher(), static::objectFactory(), $serializer, $tags);
        $checker = new ExpectationChecker($tags, $serializer);

        $references = [];
        foreach ($scenario->steps as $step) {
            try {
                $result = $runner->run($step, $references);
            } catch (FixtureUnavailableException $e) {
                $this->markTestSkipped($e->getMessage());
            }

            $differences = $checker->check($step, $result, $references);
            $this->assertSame(
                [],
                $differences,
                sprintf("%s (%s):\n- %s", $scenario->description, $path, implode("\n- ", $differences))
            );

            $references[$step->name] = $result->toReference();
        }
    }
}
