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

use Derafu\BackboneDispatcher\Service\Reflection\Inspector;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleDocChildBareTag;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleDocChildBracesLowerCase;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleDocChildBracesMixedCase;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleDocChildInlineDescriptionWithOwnSummary;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleDocChildInlineSummaryWithOwnDescription;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleDocChildMixedWithProse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Covers `getClassDoc()`/`getPublicMethods()`'s resolution of `{@inheritDoc}`/
 * `@inheritDoc`, across every real spelling and placement:
 *
 * - Whole docblock is nothing but the marker (`{@inheritDoc}` mixed case,
 *   `{@inheritdoc}` all lowercase — the exact form that broke
 *   `libredte-lib-pro`'s `DocumentComponent`, silently leaking the literal
 *   tag text into the API instead of resolving it — or the bare `@inheritDoc`
 *   block tag with no braces at all, what PHPStorm and other IDEs generate
 *   for an overridden method): adopts the parent's entire summary AND
 *   description.
 * - `{@inheritDoc}` spliced inline, alongside other own, real prose — in
 *   the summary, in the description, or both independently, in any
 *   position (its own paragraph, or mid-sentence): only that occurrence is
 *   replaced with the parent's corresponding text (summary with the
 *   parent's summary, description with the parent's description), the
 *   surrounding own text is kept untouched.
 *
 * Does not cover a bare `@inheritDoc` (no braces) mixed alongside other own
 * paragraphs, e.g. `"Pepe\n\n@inheritDoc\n\nHola"`: phpDocumentor's own
 * parser discards "Pepe"/"Hola" entirely in that shape (confirmed by direct
 * probing — summary and description both come back empty, with the tag
 * parsed separately), so there is no text left for `Inspector` to recover
 * without writing a custom docblock parser. Nothing in this codebase writes
 * a bare tag that way — the inline splice cases above always use braces.
 */
#[CoversClass(Inspector::class)]
class InspectorDocInheritanceTest extends TestCase
{
    private Inspector $inspector;

    protected function setUp(): void
    {
        $this->inspector = new Inspector();
    }

    /**
     * @return array<string, array{0: class-string}>
     */
    public static function inheritDocSpellingsProvider(): array
    {
        return [
            '{@inheritDoc} (mixed case, braces)' => [ExampleDocChildBracesMixedCase::class],
            '{@inheritdoc} (lower case, braces)' => [ExampleDocChildBracesLowerCase::class],
            '@inheritDoc (bare tag, no braces)' => [ExampleDocChildBareTag::class],
        ];
    }

    #[DataProvider('inheritDocSpellingsProvider')]
    public function testGetClassDocResolvesToTheParentsRealSummaryAndDescription(
        string $childClass
    ): void {
        $doc = $this->inspector->getClassDoc(new $childClass());

        $this->assertSame('Real class summary of the parent.', $doc['summary']);
        $this->assertStringContainsString(
            'Real class description of the parent',
            $doc['description']
        );
    }

    #[DataProvider('inheritDocSpellingsProvider')]
    public function testGetPublicMethodsResolvesToTheParentsRealMethodDoc(
        string $childClass
    ): void {
        $methods = $this->inspector->getPublicMethods(new $childClass());

        $this->assertSame(
            'Real method summary of the parent.',
            $methods['describe']['summary']
        );
        $this->assertStringContainsString(
            'Real method description of the parent',
            $methods['describe']['description']
        );
    }

    /**
     * The exact shape that was still broken after the first fix: an own,
     * real summary ("Pepe"), a description that is its own paragraph
     * ("Hola") plus a separate `{@inheritdoc}` paragraph spliced between
     * them — only the description's placeholder paragraph must resolve;
     * the own summary must stay untouched.
     */
    public function testResolvesAnInheritDocParagraphInsideAnOtherwiseOwnDescription(): void
    {
        $doc = $this->inspector->getClassDoc(
            new ExampleDocChildInlineDescriptionWithOwnSummary()
        );

        $this->assertSame('Pepe', $doc['summary']);
        $this->assertStringContainsString(
            'Real class description of the parent',
            $doc['description']
        );
        $this->assertStringContainsString('Hola', $doc['description']);
    }

    public function testResolvesAnInheritDocParagraphInsideAnOtherwiseOwnMethodDescription(): void
    {
        $methods = $this->inspector->getPublicMethods(
            new ExampleDocChildInlineDescriptionWithOwnSummary()
        );

        $this->assertSame('Pepe', $methods['describe']['summary']);
        $this->assertStringContainsString(
            'Real method description of the parent',
            $methods['describe']['description']
        );
        $this->assertStringContainsString('Hola', $methods['describe']['description']);
    }

    /**
     * The summary is nothing but the placeholder, but the docblock also
     * has its own, separate, real description ("Hola") — so the whole-
     * docblock shortcut must not apply (that would also discard "Hola"),
     * only the summary resolves.
     */
    public function testResolvesAnInheritDocSummaryAloneWhenTheDescriptionIsOwn(): void
    {
        $doc = $this->inspector->getClassDoc(
            new ExampleDocChildInlineSummaryWithOwnDescription()
        );

        $this->assertSame('Real class summary of the parent.', $doc['summary']);
        $this->assertSame('Hola', $doc['description']);
    }

    /**
     * `{@inheritDoc}` spliced mid-sentence, alongside other own text in the
     * very same paragraph — the inline splice applies regardless of where
     * in the summary the tag sits, not only when it is the whole summary.
     */
    public function testResolvesAnInheritDocTagSplicedMidSentence(): void
    {
        $doc = $this->inspector->getClassDoc(new ExampleDocChildMixedWithProse());

        $this->assertSame(
            'Own summary, not inherited. Real class summary of the parent.',
            $doc['summary']
        );
    }
}
