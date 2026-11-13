<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\BackboneDispatcher\Testing\Fixture;

use Derafu\BackboneDispatcher\Testing\Exception\FixtureUnavailableException;
use Derafu\BackboneDispatcher\Testing\Exception\InvalidScenarioException;
use Throwable;

/**
 * The data that the scenarios ask for with the `!fixture` tag, registered by
 * the dispatcher that is tested.
 *
 * A fixture is a name with a provider: a callable that receives the arguments
 * of the tag and returns the value. A fixture can need requirements, named
 * checks of the environment (for example "docker"), and a resource that has a
 * lifecycle (a server started on demand) registers a shutdown hook when it
 * starts.
 *
 * The result of a provider is not cached: a provider that is expensive to
 * build does its own caching. The result of a requirement check is cached,
 * because the environment does not change during a run.
 */
final class FixtureRegistry
{
    /**
     * @var array<string, array{provider: callable(array<string, mixed>): mixed, requires: list<string>}>
     */
    private array $fixtures = [];

    /**
     * @var array<string, callable(): ?string>
     */
    private array $requirements = [];

    /**
     * @var array<string, string|null>
     */
    private array $checked = [];

    /**
     * @var list<callable(): void>
     */
    private array $shutdownHooks = [];

    /**
     * Registers a fixture.
     *
     * @param string $name The name used in `!fixture name`.
     * @param callable(array<string, mixed>): mixed $provider Receives the
     * arguments of the tag (an empty array when it has none).
     * @param list<string> $requires The names of the requirements, see
     * `requirement()`.
     * @return self
     */
    public function fixture(string $name, callable $provider, array $requires = []): self
    {
        $this->fixtures[$name] = ['provider' => $provider, 'requires' => $requires];

        return $this;
    }

    /**
     * Registers a requirement.
     *
     * @param string $name The name used in `requires`.
     * @param callable(): ?string $check Returns null when the requirement is
     * available, or the reason why it is not (the text of the skip).
     * @return self
     */
    public function requirement(string $name, callable $check): self
    {
        $this->requirements[$name] = $check;
        unset($this->checked[$name]);

        return $this;
    }

    /**
     * Registers a hook that `shutdown()` calls, in reverse order of
     * registration.
     *
     * @param callable(): void $hook
     * @return self
     */
    public function onShutdown(callable $hook): self
    {
        $this->shutdownHooks[] = $hook;

        return $this;
    }

    /**
     * Gets the value of a fixture.
     *
     * @param string $name The fixture name.
     * @param array<string, mixed> $arguments The arguments of the tag.
     * @return mixed
     * @throws InvalidScenarioException When the fixture is not registered.
     * @throws FixtureUnavailableException When a requirement is missing.
     */
    public function resolve(string $name, array $arguments = []): mixed
    {
        if (!isset($this->fixtures[$name])) {
            throw InvalidScenarioException::forFixture($name);
        }

        $fixture = $this->fixtures[$name];

        foreach ($fixture['requires'] as $requirement) {
            $reason = $this->unavailable($requirement);
            if ($reason !== null) {
                throw FixtureUnavailableException::forRequirement($requirement, $reason);
            }
        }

        return ($fixture['provider'])($arguments);
    }

    /**
     * Gets the reason of the first requirement that is not available.
     *
     * @param string ...$requirements The names of the requirements.
     * @return string|null The reason, or null when all of them are available.
     * @throws InvalidScenarioException When a requirement is not registered.
     */
    public function unavailable(string ...$requirements): ?string
    {
        foreach ($requirements as $name) {
            if (!isset($this->requirements[$name])) {
                throw InvalidScenarioException::forRequirement($name);
            }

            if (!array_key_exists($name, $this->checked)) {
                $this->checked[$name] = ($this->requirements[$name])();
            }

            if ($this->checked[$name] !== null) {
                return $this->checked[$name];
            }
        }

        return null;
    }

    /**
     * Calls the shutdown hooks, in reverse order of registration.
     *
     * Every hook is called even if one throws: the first exception is thrown
     * after all of them ran.
     *
     * @return void
     */
    public function shutdown(): void
    {
        $hooks = array_reverse($this->shutdownHooks);
        $this->shutdownHooks = [];

        $first = null;
        foreach ($hooks as $hook) {
            try {
                $hook();
            } catch (Throwable $e) {
                $first ??= $e;
            }
        }

        if ($first !== null) {
            throw $first;
        }
    }
}
