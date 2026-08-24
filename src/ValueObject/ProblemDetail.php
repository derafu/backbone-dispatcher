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

use Derafu\BackboneDispatcher\Contract\ProblemDetailInterface;
use Derafu\BackboneDispatcher\Contract\SafeThrowableInterface;

/**
 * Encapsulates error information using RFC 7807 (Problem Details) as a
 * base, without anything specific to HTTP (no status code, no request).
 */
class ProblemDetail implements ProblemDetailInterface
{
    public function __construct(
        private readonly string $detail,
        private readonly SafeThrowableInterface $throwable,
        private readonly float $timestamp,
        private readonly string $environment,
        private readonly string $type = 'about:blank',
        private readonly ?string $title = null,
        private readonly ?string $instance = null,
        private readonly array $context = [],
        private readonly bool $debug = true
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * {@inheritDoc}
     */
    public function getTitle(): string
    {
        return $this->title ?? $this->throwable->getClass();
    }

    /**
     * {@inheritDoc}
     */
    public function getDetail(): string
    {
        return $this->detail;
    }

    /**
     * {@inheritDoc}
     */
    public function getInstance(): ?string
    {
        return $this->instance;
    }

    /**
     * {@inheritDoc}
     */
    public function getThrowable(): SafeThrowableInterface
    {
        return $this->throwable;
    }

    /**
     * {@inheritDoc}
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * {@inheritDoc}
     */
    public function getTimestamp(): float
    {
        return $this->timestamp;
    }

    /**
     * {@inheritDoc}
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * {@inheritDoc}
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * {@inheritDoc}
     */
    public function __toString(): string
    {
        $output = "# A Problem Occurred\n\n";

        $output .= "## Problem Detail\n\n";
        $output .= "- Type: `{$this->getType()}`.\n";
        $output .= "- Title: `{$this->getTitle()}`.\n";
        $output .= "- Detail: {$this->getDetail()}\n";
        $output .= "- Instance: `{$this->getInstance()}`.\n\n";

        $output .= "## Environment\n\n";
        $output .= "- Timestamp: `{$this->getTimestamp()}`.\n";
        $output .= "- Environment: `{$this->getEnvironment()}`.\n";
        $output .= "- Debug: `{$this->isDebug()}`.\n\n";

        if (!empty($this->context)) {
            $flags =
                JSON_PRETTY_PRINT
                | JSON_INVALID_UTF8_SUBSTITUTE
                | JSON_UNESCAPED_LINE_TERMINATORS
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
            ;
            $output .= "## Context\n\n";
            $output .= "```json\n" . json_encode($this->context, $flags) . "\n```\n\n";
        }

        if ($this->isDebug()) {
            $output .= "## Throwable\n\n";
            $output .= "```\n" . $this->getThrowable() . "\n```\n\n";
        }

        return $output;
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->getType(),
            'title' => $this->getTitle(),
            'detail' => $this->getDetail(),
            'instance' => $this->getInstance(),
            'extensions' => [
                'timestamp' => $this->getTimestamp(),
                'data_type' => null, // Always null: a failure never has a value to describe.
                'environment' => $this->getEnvironment(),
                'debug' => $this->isDebug(),
                'context' => $this->getContext(),
                'throwable' => $this->isDebug() ? $this->getThrowable() : null,
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
