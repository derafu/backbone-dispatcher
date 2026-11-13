<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\BackboneDispatcher\Testing\Exception;

use Derafu\Translation\Exception\Core\TranslatableLogicException;

/**
 * Exception for a scenario file with a wrong structure, a tag in the wrong
 * place, or a reference to a fixture, requirement or step that does not exist.
 *
 * It is an error of the test, not a failed expectation: the scenario cannot be
 * run, so it never reaches the assertions.
 */
class InvalidScenarioException extends TranslatableLogicException
{
    /**
     * A key that is missing or has the wrong type.
     *
     * @param string $file The scenario file.
     * @param string $key The dot path of the key, like `steps.load.dispatch`.
     * @param string $expected What the key must be, like "a map".
     * @return self
     */
    public static function forKey(string $file, string $key, string $expected): self
    {
        return new self([
            'The key {key} of the scenario {file} must be {expected}.',
            'key' => $key,
            'file' => $file,
            'expected' => $expected,
        ]);
    }

    /**
     * A key that the structure does not know.
     *
     * @param string $file The scenario file.
     * @param string $key The dot path of the key.
     * @return self
     */
    public static function forUnknownKey(string $file, string $key): self
    {
        return new self([
            'The key {key} of the scenario {file} is not allowed.',
            'key' => $key,
            'file' => $file,
        ]);
    }

    /**
     * A step name that does not match the allowed pattern.
     *
     * @param string $file The scenario file.
     * @param string $step The step name.
     * @return self
     */
    public static function forStepName(string $file, string $step): self
    {
        return new self([
            'The step name {step} of the scenario {file} is not valid. It must start with a lowercase letter and have only lowercase letters, digits and underscores.',
            'step' => $step,
            'file' => $file,
        ]);
    }

    /**
     * A step that is not exactly one of the step kinds.
     *
     * @param string $file The scenario file.
     * @param string $step The step name.
     * @return self
     */
    public static function forStepKind(string $file, string $step): self
    {
        return new self([
            'The step {step} of the scenario {file} must have exactly one of dispatch or deserialize.',
            'step' => $step,
            'file' => $file,
        ]);
    }

    /**
     * A step with more than one kind of expectation.
     *
     * @param string $file The scenario file.
     * @param string $step The step name.
     * @return self
     */
    public static function forExpectationKind(string $file, string $step): self
    {
        return new self([
            'The step {step} of the scenario {file} can have only one of expect, failure or either.',
            'step' => $step,
            'file' => $file,
        ]);
    }

    /**
     * A key that only one kind of step accepts.
     *
     * @param string $file The scenario file.
     * @param string $key The dot path of the key.
     * @param string $kind The kind of step that accepts it.
     * @return self
     */
    public static function forKindOnlyKey(string $file, string $key, string $kind): self
    {
        return new self([
            'The key {key} of the scenario {file} is only allowed in {kind} steps.',
            'key' => $key,
            'file' => $file,
            'kind' => $kind,
        ]);
    }

    /**
     * A tag that does not exist or is not allowed where it is.
     *
     * @param string $file The scenario file.
     * @param string $key The dot path where the tag is.
     * @param string $tag The tag name, without the exclamation mark.
     * @return self
     */
    public static function forTag(string $file, string $key, string $tag): self
    {
        return new self([
            'The tag {tag} is not allowed at {key} of the scenario {file}.',
            'tag' => $tag,
            'key' => $key,
            'file' => $file,
        ]);
    }

    /**
     * A reference to a step that is not before the one that uses it.
     *
     * @param string $file The scenario file.
     * @param string $key The dot path where the reference is.
     * @param string $reference The reference path.
     * @return self
     */
    public static function forReferenceToStep(string $file, string $key, string $reference): self
    {
        return new self([
            'The reference {reference} at {key} of the scenario {file} does not point to a step that comes before.',
            'reference' => $reference,
            'key' => $key,
            'file' => $file,
        ]);
    }

    /**
     * A reference to a value that the results of the previous steps do not have.
     *
     * @param string $reference The reference path.
     * @return self
     */
    public static function forMissingReferenceValue(string $reference): self
    {
        return new self([
            'The reference {reference} does not exist in the results of the previous steps.',
            'reference' => $reference,
        ]);
    }

    /**
     * A fixture that is not registered.
     *
     * @param string $name The fixture name.
     * @return self
     */
    public static function forFixture(string $name): self
    {
        return new self([
            'The fixture {name} is not registered.',
            'name' => $name,
        ]);
    }

    /**
     * A requirement that is not registered.
     *
     * @param string $name The requirement name.
     * @return self
     */
    public static function forRequirement(string $name): self
    {
        return new self([
            'The requirement {name} is not registered.',
            'name' => $name,
        ]);
    }

    /**
     * A directory of scenarios that has none, or does not exist.
     *
     * @param string $directory The directory.
     * @return self
     */
    public static function forEmptyDirectory(string $directory): self
    {
        return new self([
            'The directory {directory} has no scenarios (files with the extension yaml).',
            'directory' => $directory,
        ]);
    }
}
