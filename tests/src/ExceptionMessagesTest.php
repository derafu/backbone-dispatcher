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
use Derafu\BackboneDispatcher\Exception\InvalidEnumValueException;
use Derafu\BackboneDispatcher\Exception\InvalidOperationIdException;
use Derafu\BackboneDispatcher\Exception\InvalidParameterTypeException;
use Derafu\BackboneDispatcher\Exception\MissingOptionalDependencyException;
use Derafu\BackboneDispatcher\Exception\MissingParameterException;
use Derafu\BackboneDispatcher\Exception\NoDeserializerFoundException;
use Derafu\BackboneDispatcher\Exception\OperationNotAllowedException;
use Derafu\BackboneDispatcher\Exception\OperationNotFoundException;
use Derafu\BackboneDispatcher\Exception\UnsupportedDataTypeException;
use Derafu\BackboneDispatcher\Testing\Exception\FixtureUnavailableException;
use Derafu\BackboneDispatcher\Testing\Exception\InvalidScenarioException;
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
#[CoversClass(FixtureUnavailableException::class)]
#[CoversClass(FromArrayMethodNotFoundException::class)]
#[CoversClass(InvalidDiscoveryIdException::class)]
#[CoversClass(InvalidEnumValueException::class)]
#[CoversClass(InvalidOperationIdException::class)]
#[CoversClass(InvalidParameterTypeException::class)]
#[CoversClass(InvalidScenarioException::class)]
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
            'enum value' => [static fn () => InvalidEnumValueException::forEnum('Example', 'x', ['a', 'b']), 'The value "x" is not valid for the enum Example. The allowed values are: "a", "b".', 'El valor "x" no es válido para el enum Example. Los valores permitidos son: "a", "b".'],
            'parameter type' => [static fn () => InvalidParameterTypeException::forParameter('amount', 'int'), 'The parameter amount must be of type int.', 'El parámetro amount debe ser de tipo int.'],
            'missing parameter' => [static fn () => MissingParameterException::forParameter('amount', 'int'), 'The parameter amount of type int is missing from the request data.', 'Falta el parámetro amount de tipo int en los datos de la solicitud.'],
            'optional dependency' => [static fn () => MissingOptionalDependencyException::forFeature('Example::validate()', 'opis/json-schema'), 'Example::validate() requires opis/json-schema, which is not installed. Run: composer require --dev opis/json-schema', 'Example::validate() requiere opis/json-schema, que no está instalado. Ejecuta: composer require --dev opis/json-schema'],
            'no deserializer' => [static fn () => NoDeserializerFoundException::forClass('Example'), 'No deserializer was found able to build an instance of Example.', 'No se encontró un deserializador capaz de construir una instancia de Example.'],
            'operation denied' => [static fn () => OperationNotAllowedException::forOperation('p', 'c', 'w', 'run'), 'The operation run of p.c.w is not allowed to be dispatched.', 'La operación run de p.c.w no tiene permitido ser despachada.'],
            'missing operation' => [static fn () => OperationNotFoundException::forOperation('p', 'c', 'w', 'run'), 'The operation run does not exist in p.c.w.', 'La operación run no existe en p.c.w.'],
            'deserializer shape' => [static fn () => UnsupportedDataTypeException::forDeserializer('Example', 'array'), 'Example requires array data.', 'Example requiere datos array.'],
            'missing keys' => [static fn () => UnsupportedDataTypeException::forMissingKeys('Example', ['name', 'parent.id']), 'Example is missing required key(s): name, parent.id.', 'A Example le faltan claves obligatorias: name, parent.id.'],
            'schema violations' => [static fn () => UnsupportedDataTypeException::forSchema('Example', ['Invalid name.', 'Missing id.']), 'Example does not match the expected schema: Invalid name. Missing id..', 'Example no coincide con el esquema esperado: Invalid name. Missing id..'],
            'scenario key' => [static fn () => InvalidScenarioException::forKey('a.yaml', 'steps', 'a map'), 'The key steps of the scenario a.yaml must be a map.', 'La clave steps del escenario a.yaml debe ser a map.'],
            'scenario unknown key' => [static fn () => InvalidScenarioException::forUnknownKey('a.yaml', 'other'), 'The key other of the scenario a.yaml is not allowed.', 'La clave other del escenario a.yaml no está permitida.'],
            'scenario step name' => [static fn () => InvalidScenarioException::forStepName('a.yaml', 'Bad'), 'The step name Bad of the scenario a.yaml is not valid. It must start with a lowercase letter and have only lowercase letters, digits and underscores.', 'El nombre del paso Bad del escenario a.yaml no es válido. Debe comenzar con una letra minúscula y tener solo letras minúsculas, dígitos y guiones bajos.'],
            'scenario step kind' => [static fn () => InvalidScenarioException::forStepKind('a.yaml', 'first'), 'The step first of the scenario a.yaml must have exactly one of dispatch or deserialize.', 'El paso first del escenario a.yaml debe tener exactamente uno de dispatch o deserialize.'],
            'scenario expectation kind' => [static fn () => InvalidScenarioException::forExpectationKind('a.yaml', 'first'), 'The step first of the scenario a.yaml can have only one of expect, failure or either.', 'El paso first del escenario a.yaml puede tener solo uno de expect, failure o either.'],
            'scenario kind only key' => [static fn () => InvalidScenarioException::forKindOnlyKey('a.yaml', 'steps.a.expect.call', 'deserialize'), 'The key steps.a.expect.call of the scenario a.yaml is only allowed in deserialize steps.', 'La clave steps.a.expect.call del escenario a.yaml solo está permitida en pasos deserialize.'],
            'scenario tag' => [static fn () => InvalidScenarioException::forTag('a.yaml', 'steps.a.expect.b', 'any'), 'The tag any is not allowed at steps.a.expect.b of the scenario a.yaml.', 'La etiqueta any no está permitida en steps.a.expect.b del escenario a.yaml.'],
            'scenario reference to a step' => [static fn () => InvalidScenarioException::forReferenceToStep('a.yaml', 'steps.a.dispatch', 'b.value'), 'The reference b.value at steps.a.dispatch of the scenario a.yaml does not point to a step that comes before.', 'La referencia b.value en steps.a.dispatch del escenario a.yaml no apunta a un paso anterior.'],
            'scenario missing reference' => [static fn () => InvalidScenarioException::forMissingReferenceValue('a.value.id'), 'The reference a.value.id does not exist in the results of the previous steps.', 'La referencia a.value.id no existe en los resultados de los pasos anteriores.'],
            'scenario fixture' => [static fn () => InvalidScenarioException::forFixture('cal'), 'The fixture cal is not registered.', 'El fixture cal no está registrado.'],
            'scenario requirement' => [static fn () => InvalidScenarioException::forRequirement('docker'), 'The requirement docker is not registered.', 'El requisito docker no está registrado.'],
            'scenario empty directory' => [static fn () => InvalidScenarioException::forEmptyDirectory('/tmp/scenarios'), 'The directory /tmp/scenarios has no scenarios (files with the extension yaml).', 'El directorio /tmp/scenarios no tiene escenarios (archivos con la extensión yaml).'],
            'fixture unavailable' => [static fn () => FixtureUnavailableException::forRequirement('docker', 'Docker is not running.'), 'The requirement docker is not available: Docker is not running.', 'El requisito docker no está disponible: Docker is not running.'],
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

    public function testEnumValueErrorShowsALongValueTruncatedAndChainsTheCause(): void
    {
        $cause = new RuntimeException('Rejected');
        $exception = InvalidEnumValueException::forEnum('Example', str_repeat('x', 300), [1, 2], $cause);

        $this->assertSame($cause, $exception->getPrevious());
        $this->assertSame(
            'The value "' . str_repeat('x', 96) . '... is not valid for the enum Example. The allowed values are: 1, 2.',
            $exception->getMessage()
        );
    }

    public function testEnumValueErrorShowsAValueWithInvalidEncodingWithoutFailing(): void
    {
        $exception = InvalidEnumValueException::forEnum('Example', "\xB1", ['a']);

        $this->assertStringContainsString('is not valid for the enum Example', $exception->getMessage());
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
