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

use Derafu\BackboneDispatcher\Exception\InvalidOperationIdException;
use Derafu\BackboneDispatcher\ValueObject\OperationRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OperationRequest::class)]
#[UsesClass(InvalidOperationIdException::class)]
class OperationRequestTest extends TestCase
{
    public function testGetIdUsesDoubleColonBeforeTheOperation(): void
    {
        $request = new OperationRequest('billing', 'invoice', 'builder', 'createDraft');

        $this->assertSame('billing.invoice.builder::createDraft', $request->getId());
    }

    public function testFromIdParsesTheDoubleColonFormat(): void
    {
        $request = OperationRequest::fromId(
            'billing.invoice.builder::createDraft',
            ['number' => 'F-001']
        );

        $this->assertSame('billing', $request->getPackage());
        $this->assertSame('invoice', $request->getComponent());
        $this->assertSame('builder', $request->getWorker());
        $this->assertSame('createDraft', $request->getOperation());
        $this->assertSame(['number' => 'F-001'], $request->getParameters());
    }

    public function testFromIdRoundTripsWithGetId(): void
    {
        $request = OperationRequest::fromId('billing.invoice.builder::createDraft');

        $this->assertSame('billing.invoice.builder::createDraft', $request->getId());
    }

    public function testFromIdThrowsWhenThereIsNoDoubleColonAtAll(): void
    {
        $this->expectException(InvalidOperationIdException::class);

        OperationRequest::fromId('billing.invoice.builder.createDraft');
    }

    public function testFromIdThrowsWhenASingleColonIsUsedInstead(): void
    {
        // The old, pre-`::` format must not be silently accepted.
        $this->expectException(InvalidOperationIdException::class);

        OperationRequest::fromId('billing.invoice.builder:createDraft');
    }

    public function testFromIdThrowsWhenThePathHasTheWrongNumberOfSegments(): void
    {
        $this->expectException(InvalidOperationIdException::class);

        OperationRequest::fromId('billing.invoice::createDraft');
    }

    public function testFromIdThrowsWhenAnySegmentIsEmpty(): void
    {
        $this->expectException(InvalidOperationIdException::class);

        OperationRequest::fromId('billing..builder::createDraft');
    }

    public function testFromIdThrowsWhenTheOperationIsEmpty(): void
    {
        $this->expectException(InvalidOperationIdException::class);

        OperationRequest::fromId('billing.invoice.builder::');
    }
}
