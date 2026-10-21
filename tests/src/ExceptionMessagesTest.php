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

use Derafu\BackboneDispatcher\Exception\ClassNotFoundException;
use Derafu\BackboneDispatcher\Exception\FromArrayMethodNotFoundException;
use Derafu\BackboneDispatcher\Exception\InvalidDiscoveryIdException;
use Derafu\BackboneDispatcher\Exception\InvalidOperationIdException;
use Derafu\BackboneDispatcher\Exception\InvalidParameterTypeException;
use Derafu\BackboneDispatcher\Exception\MissingOptionalDependencyException;
use Derafu\BackboneDispatcher\Exception\MissingParameterException;
use Derafu\BackboneDispatcher\Exception\NoDeserializerFoundException;
use Derafu\BackboneDispatcher\Exception\OperationNotAllowedException;
use Derafu\BackboneDispatcher\Exception\OperationNotFoundException;
use Derafu\BackboneDispatcher\Exception\UnsupportedDataTypeException;
use Derafu\BackboneDispatcher\Translation\BackboneDispatcherTranslationResourceProvider;
use Derafu\Translation\Contract\TranslatableInterface;
use Derafu\Translation\TranslatorFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

#[CoversClass(ClassNotFoundException::class)]
#[CoversClass(FromArrayMethodNotFoundException::class)]
#[CoversClass(InvalidDiscoveryIdException::class)]
#[CoversClass(InvalidOperationIdException::class)]
#[CoversClass(InvalidParameterTypeException::class)]
#[CoversClass(MissingOptionalDependencyException::class)]
#[CoversClass(MissingParameterException::class)]
#[CoversClass(NoDeserializerFoundException::class)]
#[CoversClass(OperationNotAllowedException::class)]
#[CoversClass(OperationNotFoundException::class)]
#[CoversClass(UnsupportedDataTypeException::class)]
#[UsesClass(BackboneDispatcherTranslationResourceProvider::class)]
class ExceptionMessagesTest extends TestCase
{
    /** @return array<string, array{callable(): (Throwable&TranslatableInterface), string, string}> */
    public static function messages(): array
    {
        return [
            'missing class' => [static fn () => ClassNotFoundException::forClass('MissingClass'), 'Class MissingClass does not exist.', 'La clase MissingClass no existe.'],
            'missing factory method' => [static fn () => FromArrayMethodNotFoundException::forClass('Example'), 'Method Example::fromArray() does not exist.', 'El método Example::fromArray() no existe.'],
            'discovery identifier' => [static fn () => InvalidDiscoveryIdException::forId('a.b.c.d'), 'The discovery id a.b.c.d is not valid. It must have the structure: package[.component[.worker[::operation]]].', 'El id de descubrimiento a.b.c.d no es válido. Debe tener la estructura: package[.component[.worker[::operation]]].'],
            'operation identifier' => [static fn () => InvalidOperationIdException::forId('invalid'), 'The operation id invalid is not valid. It must have the structure: package.component.worker::operation.', 'El id de operación invalid no es válido. Debe tener la estructura: package.component.worker::operation.'],
            'parameter type' => [static fn () => InvalidParameterTypeException::forParameter('amount', 'int'), 'The parameter amount must be of type int.', 'El parámetro amount debe ser de tipo int.'],
            'missing parameter' => [static fn () => MissingParameterException::forParameter('amount', 'int'), 'The parameter amount of type int is missing from the request data.', 'Falta el parámetro amount de tipo int en los datos de la solicitud.'],
            'optional dependency' => [static fn () => MissingOptionalDependencyException::forFeature('Example::validate()', 'opis/json-schema'), 'Example::validate() requires opis/json-schema, which is not installed. Run: composer require --dev opis/json-schema', 'Example::validate() requiere opis/json-schema, que no está instalado. Ejecuta: composer require --dev opis/json-schema'],
            'no deserializer' => [static fn () => NoDeserializerFoundException::forClass('Example'), 'No deserializer was found able to build an instance of Example.', 'No se encontró un deserializador capaz de construir una instancia de Example.'],
            'operation denied' => [static fn () => OperationNotAllowedException::forOperation('p', 'c', 'w', 'run'), 'The operation run of p.c.w is not allowed to be dispatched.', 'La operación run de p.c.w no tiene permitido ser despachada.'],
            'missing operation' => [static fn () => OperationNotFoundException::forOperation('p', 'c', 'w', 'run'), 'The operation run does not exist in p.c.w.', 'La operación run no existe en p.c.w.'],
            'deserializer shape' => [static fn () => UnsupportedDataTypeException::forDeserializer('Example', 'array'), 'Example requires array data.', 'Example requiere datos array.'],
            'missing keys' => [static fn () => UnsupportedDataTypeException::forMissingKeys('Example', ['name', 'parent.id']), 'Example is missing required key(s): name, parent.id.', 'A Example le faltan claves obligatorias: name, parent.id.'],
            'schema violations' => [static fn () => UnsupportedDataTypeException::forSchema('Example', ['Invalid name.', 'Missing id.']), 'Example does not match the expected schema: Invalid name. Missing id..', 'Example no coincide con el esquema esperado: Invalid name. Missing id..'],
        ];
    }

    /** @param callable(): (Throwable&TranslatableInterface) $create */
    #[DataProvider('messages')]
    public function testExceptionFactoriesPreserveParametersAndTranslateTheirMessages(callable $create, string $english, string $spanish): void
    {
        $exception = $create();
        $translator = TranslatorFactory::create('es', resourceProviders: [new BackboneDispatcherTranslationResourceProvider()]);

        $this->assertSame($english, $exception->getMessage());
        $this->assertSame($spanish, $exception->trans($translator));
        $this->assertSame(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testAllDeserializerRejectionsAreReportedInOrderAndTheLastIsChained(): void
    {
        $first = new RuntimeException('Expected an array');
        $last = new RuntimeException('Missing name');
        $exception = NoDeserializerFoundException::forClass('First|Second', ['First' => $first, 'Second' => $last]);
        $translator = TranslatorFactory::create('es', resourceProviders: [new BackboneDispatcherTranslationResourceProvider()]);

        $this->assertSame($last, $exception->getPrevious());
        $this->assertSame('No deserializer was found able to build an instance of First|Second. Every candidate was tried and rejected: First (Expected an array); Second (Missing name).', $exception->getMessage());
        $this->assertSame('No se encontró un deserializador capaz de construir una instancia de First|Second. Se probaron todos los candidatos y fueron rechazados: First (Expected an array); Second (Missing name).', $exception->trans($translator));
    }
}
