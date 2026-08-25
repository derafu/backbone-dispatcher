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

use Derafu\BackboneDispatcher\Abstract\AbstractDeserializer;
use Derafu\BackboneDispatcher\Exception\UnsupportedDataTypeException;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleShapeAssertingDeserializer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AbstractDeserializer::class)]
#[UsesClass(UnsupportedDataTypeException::class)]
class AbstractDeserializerTest extends TestCase
{
    private ExampleShapeAssertingDeserializer $deserializer;

    protected function setUp(): void
    {
        $this->deserializer = new ExampleShapeAssertingDeserializer();
    }

    public function testAssertArrayReturnsArrayDataUnchanged(): void
    {
        $data = ['name' => 'folios'];

        $this->assertSame($data, $this->deserializer->callAssertArray($data));
    }

    public function testAssertArrayThrowsUnsupportedDataTypeExceptionForStringData(): void
    {
        $this->expectException(UnsupportedDataTypeException::class);

        $this->deserializer->callAssertArray('not-an-array');
    }

    public function testAssertStringReturnsStringDataUnchanged(): void
    {
        $this->assertSame('folios', $this->deserializer->callAssertString('folios'));
    }

    public function testAssertStringThrowsUnsupportedDataTypeExceptionForArrayData(): void
    {
        $this->expectException(UnsupportedDataTypeException::class);

        $this->deserializer->callAssertString(['not' => 'a string']);
    }

    public function testAssertKeysReturnsDataUnchangedWhenAFlatListOfKeysIsAllPresent(): void
    {
        $data = ['tipo' => 'libro_ventas', 'caratula' => [], 'detalle' => []];

        $this->assertSame(
            $data,
            $this->deserializer->callAssertKeys($data, ['tipo', 'caratula', 'detalle']),
        );
    }

    public function testAssertKeysThrowsListingEveryMissingKeyFromAFlatList(): void
    {
        $this->expectException(UnsupportedDataTypeException::class);
        $this->expectExceptionMessage(sprintf(
            '%s is missing required key(s): caratula, detalle.',
            ExampleShapeAssertingDeserializer::class,
        ));

        $this->deserializer->callAssertKeys(['tipo' => 'libro_ventas'], [
            'tipo',
            'caratula',
            'detalle',
        ]);
    }

    public function testAssertKeysAcceptsAKeyPresentWithANullValue(): void
    {
        $data = ['tipo' => null];

        $this->assertSame($data, $this->deserializer->callAssertKeys($data, ['tipo']));
    }

    public function testAssertKeysValidatesNestedRequirementsAndReturnsDataUnchangedWhenTheyMatch(): void
    {
        $data = [
            'tipo' => 'envio_recibos',
            'certificate' => ['data' => '...', 'password' => '...'],
        ];

        $this->assertSame($data, $this->deserializer->callAssertKeys($data, [
            'tipo',
            'certificate' => ['data', 'password'],
        ]));
    }

    public function testAssertKeysReportsANestedMissingKeyWithADotSeparatedPath(): void
    {
        $this->expectException(UnsupportedDataTypeException::class);
        $this->expectExceptionMessage(sprintf(
            '%s is missing required key(s): certificate.password.',
            ExampleShapeAssertingDeserializer::class,
        ));

        $this->deserializer->callAssertKeys(
            ['tipo' => 'envio_recibos', 'certificate' => ['data' => '...']],
            ['tipo', 'certificate' => ['data', 'password']],
        );
    }

    public function testAssertKeysReportsTheWholeNestedKeyMissingWhenItIsNotAnArrayAtAll(): void
    {
        $this->expectException(UnsupportedDataTypeException::class);
        $this->expectExceptionMessage(sprintf(
            '%s is missing required key(s): certificate.',
            ExampleShapeAssertingDeserializer::class,
        ));

        $this->deserializer->callAssertKeys(
            ['tipo' => 'envio_recibos', 'certificate' => 'not-an-array'],
            ['tipo', 'certificate' => ['data', 'password']],
        );
    }

    public function testAssertSchemaReturnsDataUnchangedWhenItMatches(): void
    {
        $data = ['tipo' => 'libro_ventas', 'folio' => 1];

        $schema = [
            'type' => 'object',
            'properties' => [
                'tipo' => ['type' => 'string'],
                'folio' => ['type' => 'integer', 'minimum' => 1],
            ],
            'required' => ['tipo', 'folio'],
        ];

        $this->assertSame($data, $this->deserializer->callAssertSchema($data, $schema));
    }

    public function testAssertSchemaThrowsWithFormattedMessagesWhenItDoesNotMatch(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'tipo' => ['type' => 'string'],
                'folio' => ['type' => 'integer', 'minimum' => 1],
            ],
            'required' => ['tipo', 'folio'],
        ];

        $this->expectException(UnsupportedDataTypeException::class);
        $this->expectExceptionMessageMatches(sprintf(
            '/^%s does not match the expected schema: .+\.$/',
            preg_quote(ExampleShapeAssertingDeserializer::class, '/'),
        ));

        // Two independent violations at once (missing `tipo`, wrong type
        // for `folio`) — both should end up in the message, since
        // assertSchema() asks the validator for more than just the first
        // error it finds.
        $this->deserializer->callAssertSchema(['folio' => '0'], $schema);
    }
}
