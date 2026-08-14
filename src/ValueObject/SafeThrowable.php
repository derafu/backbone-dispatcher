<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\BackboneDispatcher\ValueObject;

use Derafu\BackboneDispatcher\Contract\SafeThrowableInterface;
use Throwable;

/**
 * A safe, serializable snapshot of a `Throwable`.
 *
 * Ported from `derafu/http`'s `SafeThrowable`. The only change: the
 * original obfuscates file paths using a `ParameterBagInterface` (project
 * dir), which is a Symfony DI concept this package does not otherwise
 * depend on. Here, `$projectDir` is passed as a plain optional string
 * instead, so the caller decides how (or whether) to obtain it.
 */
class SafeThrowable implements SafeThrowableInterface
{
    public function __construct(
        private readonly string $class,
        private readonly int $code,
        private readonly string $message,
        private readonly string $file,
        private readonly int $line,
        private readonly array $trace,
        private readonly ?SafeThrowableInterface $previous
    ) {
    }

    /**
     * Builds a safe snapshot of a `Throwable`, following its `previous`
     * chain recursively.
     *
     * @param Throwable $throwable
     * @param string|null $projectDir If given, file paths under it are
     * rewritten as `project_dir:...` instead of the absolute path.
     * @return self
     */
    public static function fromThrowable(
        Throwable $throwable,
        ?string $projectDir = null
    ): self {
        // Normalize the code and message. This handles the case where the
        // code is not an integer, typically when it's a string, for example
        // in PDOException.
        $realCode = $throwable->getCode();
        if (is_numeric($realCode)) {
            $code = (int) $realCode;
            $message = $throwable->getMessage();
        } else {
            $code = 0;
            $message = $realCode . ' - ' . $throwable->getMessage();
        }

        return new self(
            class: get_class($throwable),
            code: $code,
            message: $message,
            file: self::obfuscatePath($throwable->getFile(), $projectDir),
            line: $throwable->getLine(),
            trace: array_map(function (array $frame) use ($projectDir) {
                if (isset($frame['file'])) {
                    $frame['file'] = self::obfuscatePath($frame['file'], $projectDir);
                }
                unset($frame['args']);

                return $frame;
            }, $throwable->getTrace()),
            previous: $throwable->getPrevious()
                ? self::fromThrowable($throwable->getPrevious(), $projectDir)
                : null
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * {@inheritDoc}
     */
    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * {@inheritDoc}
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * {@inheritDoc}
     */
    public function getFile(): string
    {
        return $this->file;
    }

    /**
     * {@inheritDoc}
     */
    public function getLine(): int
    {
        return $this->line;
    }

    /**
     * {@inheritDoc}
     */
    public function getTrace(): array
    {
        return $this->trace;
    }

    /**
     * {@inheritDoc}
     */
    public function getTraceAsString(): string
    {
        $output = [];

        foreach ($this->trace as $index => $frame) {
            $file = $frame['file'] ?? '[internal]';
            $line = $frame['line'] ?? '?';
            $function = $frame['function'] ?? '';
            $class = $frame['class'] ?? '';
            $type = $frame['type'] ?? '';

            $output[] = "#$index $file($line): $class$type$function()";
        }

        return implode("\n", $output);
    }

    /**
     * {@inheritDoc}
     */
    public function getPrevious(): ?SafeThrowableInterface
    {
        return $this->previous;
    }

    /**
     * {@inheritDoc}
     */
    public function __toString(): string
    {
        $output = "Class: {$this->class}\n";
        $output .= "Message: {$this->message}\n";
        $output .= "Code: {$this->code}\n";
        $output .= "File: {$this->file} ({$this->line})\n\n";
        $output .= "Stack trace:\n" . $this->getTraceAsString() . "\n";

        if ($this->previous) {
            $output .= "\nCaused by:\n" . $this->previous->__toString();
        }

        return $output;
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'class' => $this->class,
            'code' => $this->code,
            'message' => $this->message,
            'file' => $this->file,
            'line' => $this->line,
            'trace' => $this->trace,
            'previous' => $this->previous,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Obfuscates the project directory portion of a file path, if given.
     *
     * @param string $path
     * @param string|null $projectDir
     * @return string
     */
    private static function obfuscatePath(string $path, ?string $projectDir): string
    {
        if ($projectDir === null) {
            return $path;
        }

        return str_replace($projectDir . '/', 'project_dir:', $path);
    }
}
