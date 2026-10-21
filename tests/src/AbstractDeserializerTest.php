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

use Composer\Autoload\ClassLoader;
use Derafu\BackboneDispatcher\Abstract\AbstractDeserializer;
use Derafu\BackboneDispatcher\Exception\MissingOptionalDependencyException;
use Derafu\BackboneDispatcher\Exception\UnsupportedDataTypeException;
use Derafu\BackboneDispatcher\Translation\BackboneDispatcherTranslationResourceProvider;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleShapeAssertingDeserializer;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;
use Derafu\Translation\TranslatorFactory;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AbstractDeserializer::class)]
#[UsesClass(UnsupportedDataTypeException::class)]
#[UsesClass(MissingOptionalDependencyException::class)]
#[UsesClass(BackboneDispatcherTranslationResourceProvider::class)]
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

    public function testEmptySchemaIsRejectedWithATranslatedConfigurationException(): void
    {
        try {
            $this->deserializer->callAssertSchema(['name' => 'example'], []);
            $this->fail('An empty array must not be accepted as a schema object.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('The JSON schema must be an object.', $exception->getMessage());
            $translator = TranslatorFactory::create('es', resourceProviders: [new BackboneDispatcherTranslationResourceProvider()]);
            $this->assertSame('El esquema JSON debe ser un objeto.', $exception->trans($translator));
        }
    }

    public function testRootSchemaViolationIncludesTheRootPointerAndValidationMessage(): void
    {
        try {
            $this->deserializer->callAssertSchema(['name' => 'example'], ['type' => 'string']);
            $this->fail('Object data must not satisfy a string schema.');
        } catch (UnsupportedDataTypeException $exception) {
            $this->assertSame(
                ExampleShapeAssertingDeserializer::class . ' does not match the expected schema: /: The data (object) must match the type: string.',
                $exception->getMessage()
            );
        }
    }

    /** @return array<string, array{array<array-key, mixed>, string, string}> */
    public static function invalidRequiredKeys(): array
    {
        return [
            'boolean key name' => [[true], 'int|string', 'bool'],
            'array instead of a flat key name' => [[[]], 'int|string', 'array'],
            'string instead of nested keys' => [['parent' => 'child'], 'array', 'string'],
            'nested invalid key name' => [['parent' => [false]], 'int|string', 'bool'],
        ];
    }

    /** @param array<array-key, mixed> $keys */
    #[DataProvider('invalidRequiredKeys')]
    public function testInvalidKeySpecificationsHaveATranslatedException(array $keys, string $expected, string $type): void
    {
        try {
            $this->deserializer->callAssertKeys(['parent' => []], $keys);
            $this->fail('Invalid key specifications must be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Invalid required keys specification: expected ' . $expected . '; ' . $type . ' given.', $exception->getMessage());
            $translator = TranslatorFactory::create('es', resourceProviders: [new BackboneDispatcherTranslationResourceProvider()]);
            $this->assertSame('Especificación de claves obligatorias inválida: se esperaba ' . $expected . '; se recibió ' . $type . '.', $exception->trans($translator));
        }
    }

    public function testNestedDataMayUseIntegerKeysAndNullValues(): void
    {
        $data = ['parent' => [2 => null, 'name' => 'value']];
        $this->assertSame($data, $this->deserializer->callAssertKeys($data, ['parent' => [2, 'name']]));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSchemaValidationReportsItsMissingOptionalDependency(): void
    {
        $this->assertFalse(class_exists(Validator::class, false));
        // Load the exception's translation dependencies before disabling the
        // Composer loaders in this isolated process.
        $expected = MissingOptionalDependencyException::forFeature(
            ExampleShapeAssertingDeserializer::class . '::assertSchema()',
            'opis/json-schema'
        );
        $loaders = ClassLoader::getRegisteredLoaders();
        $this->assertNotEmpty($loaders);
        foreach ($loaders as $loader) {
            $loader->unregister();
        }
        $failure = null;
        try {
            $this->deserializer->callAssertSchema(['name' => 'example'], ['type' => 'object']);
        } catch (MissingOptionalDependencyException $exception) {
            $failure = $exception;
        } finally {
            foreach (array_reverse($loaders) as $loader) {
                $loader->register(true);
            }
        }

        $this->assertInstanceOf(MissingOptionalDependencyException::class, $failure);
        $this->assertSame($expected->getMessage(), $failure->getMessage());
        $this->assertStringContainsString('composer require --dev opis/json-schema', $failure->getMessage());
    }
}
