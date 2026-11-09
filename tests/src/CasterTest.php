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
use Derafu\BackboneDispatcher\Exception\InvalidEnumValueException;
use Derafu\BackboneDispatcher\Exception\InvalidParameterTypeException;
use Derafu\BackboneDispatcher\Service\Deserialization\FromArrayDeserializer;
use Derafu\BackboneDispatcher\Service\Deserialization\ObjectFactoryRegistry;
use Derafu\BackboneDispatcher\Service\Resolution\Caster;
use Derafu\BackboneDispatcher\Translation\BackboneDispatcherTranslationResourceProvider;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleBackedEnum;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleBag;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleGreeting;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleGreetingDeserializer;
use Derafu\TestsBackboneDispatcher\Fixture\ExampleIntBackedEnum;
use Derafu\TestsBackboneDispatcher\Fixture\ExamplePureEnum;
use Derafu\Translation\TranslatorFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(Caster::class)]
#[UsesClass(AbstractDeserializer::class)]
#[UsesClass(ObjectFactoryRegistry::class)]
#[UsesClass(FromArrayDeserializer::class)]
#[UsesClass(InvalidEnumValueException::class)]
#[UsesClass(InvalidParameterTypeException::class)]
#[UsesClass(BackboneDispatcherTranslationResourceProvider::class)]
class CasterTest extends TestCase
{
    private Caster $caster;

    protected function setUp(): void
    {
        $this->caster = new Caster(
            new ObjectFactoryRegistry(fallback: new FromArrayDeserializer())
        );
    }

    public function testNullIsAlwaysReturnedAsNull(): void
    {
        $this->assertNull($this->caster->cast(null, 'string', 'int'));
    }

    public function testSameFromAndToTypeReturnsValueUnchanged(): void
    {
        $this->assertSame('5', $this->caster->cast('5', 'string', 'string'));
    }

    #[TestWith(['5', 'int', 5])]
    #[TestWith(['5.5', 'float', 5.5])]
    #[TestWith(['1', 'bool', true])]
    #[TestWith(['', 'bool', false])]
    public function testCastsStringToScalarTypes(string $value, string $to, mixed $expected): void
    {
        $this->assertSame($expected, $this->caster->cast($value, 'string', $to));
    }

    public function testCastingObjectToArrayReturnsTheArrayUnchanged(): void
    {
        $data = ['name' => 'folios', 'amount' => 3];

        $this->assertSame($data, $this->caster->cast($data, 'object', 'array'));
    }

    public function testCastingObjectToAClassCreatesItThroughTheObjectFactoryRegistry(): void
    {
        $bag = $this->caster->cast(
            ['name' => 'folios', 'amount' => 3],
            'object',
            ExampleBag::class
        );

        $this->assertInstanceOf(ExampleBag::class, $bag);
        $this->assertSame('folios', $bag->getName());
        $this->assertSame(3, $bag->getAmount());
    }

    #[TestWith(['string', 'string'])]
    #[TestWith(['float', 'number'])]
    #[TestWith(['int', 'integer'])]
    #[TestWith(['bool', 'boolean'])]
    #[TestWith(['array', 'array'])]
    #[TestWith([ExampleBag::class, 'object'])]
    public function testResolvesPhpTypeToGenericTypeName(string $type, string $expected): void
    {
        $this->assertSame($expected, $this->caster->resolveType($type));
    }

    /** @param list<string> $expected */
    #[TestWith(['int|string', ['integer', 'string']])]
    #[TestWith(['int|string|null', ['integer', 'string', 'null']])]
    public function testResolvesScalarUnionToArrayOfGenericTypeNames(
        string $type,
        array $expected
    ): void {
        $this->assertSame($expected, $this->caster->resolveType($type));
    }

    public function testResolvesMixedUnionKeepingEveryDistinctBucket(): void
    {
        $this->assertSame(
            ['object', 'string'],
            $this->caster->resolveType(ExampleBag::class . '|string')
        );
    }

    #[TestWith(['string', 'string'])]
    #[TestWith(['float', 'number'])]
    #[TestWith(['int', 'integer'])]
    #[TestWith(['bool', 'boolean'])]
    #[TestWith(['array', 'array'])]
    #[TestWith([ExampleBag::class, 'object'])]
    public function testResolveCastStrategyMatchesResolveTypeForSingleTypes(
        string $type,
        string $expected
    ): void {
        $this->assertSame($expected, $this->caster->resolveCastStrategy($type));
    }

    public function testResolveCastStrategyKeepsObjectRoutingForMixedUnion(): void
    {
        $this->assertSame(
            'object',
            $this->caster->resolveCastStrategy(ExampleBag::class . '|string')
        );
    }

    public function testResolveCastStrategyIsNativeForScalarOnlyUnion(): void
    {
        $this->assertSame('native', $this->caster->resolveCastStrategy('int|string'));
    }

    #[TestWith([1])]
    #[TestWith(['RM'])]
    public function testCastPassesThroughNativeValuesForScalarUnion(mixed $value): void
    {
        $this->assertSame($value, $this->caster->cast($value, 'native', 'int|string'));
    }

    /** @return array<string, array{mixed, string, string, string}> */
    public static function incompatibleValues(): array
    {
        return [
            'array instead of string' => [[], 'string', 'int', 'string'],
            'integer instead of string' => [12, 'string', 'float', 'string'],
            'boolean instead of string' => [false, 'string', 'bool', 'string'],
            'same-type shortcut still requires a string' => [[], 'string', 'string', 'string'],
            'string instead of collection' => ['bad', 'array_of_object', ExampleBag::class . '[]', 'array'],
            'invalid collection element' => [[true], 'array_of_object', ExampleBag::class . '[]', 'array<string, mixed>|string|null'],
            'positional object data' => [[[1]], 'array_of_object', ExampleBag::class . '[]', 'array<string, mixed>|string|null'],
            'object instead of serialized data' => [new stdClass(), 'object', ExampleBag::class, 'array<string, mixed>|string|null'],
            'boolean instead of serialized data' => [false, 'object', ExampleBag::class, 'array<string, mixed>|string|null'],
            'numeric object data key' => [[0 => 'bad'], 'object', ExampleBag::class, 'array<string, mixed>|string|null'],
        ];
    }

    #[DataProvider('incompatibleValues')]
    public function testIncompatibleValuesHaveATranslatedTypeError(mixed $value, string $from, string $to, string $expected): void
    {
        try {
            $this->caster->cast($value, $from, $to);
            $this->fail('Incompatible values must be rejected before conversion.');
        } catch (InvalidParameterTypeException $exception) {
            $this->assertSame('The parameter value must be of type ' . $expected . '.', $exception->getMessage());
            $translator = TranslatorFactory::create('es', resourceProviders: [new BackboneDispatcherTranslationResourceProvider()]);
            $this->assertSame('El parámetro value debe ser de tipo ' . $expected . '.', $exception->trans($translator));
        }
    }

    public function testObjectCollectionsPreserveKeysAndDeserializeEachEntry(): void
    {
        $result = $this->caster->cast([
            'first' => ['name' => 'folios', 'amount' => 3],
            7 => ['name' => 'certificates', 'amount' => 2],
        ], 'array_of_object', ExampleBag::class . '[]');

        $this->assertIsArray($result);
        $this->assertSame(['first', 7], array_keys($result));
        $this->assertInstanceOf(ExampleBag::class, $result['first']);
        $this->assertSame(['name' => 'folios', 'amount' => 3], $result['first']->toArray());
        $this->assertInstanceOf(ExampleBag::class, $result[7]);
        $this->assertSame(['name' => 'certificates', 'amount' => 2], $result[7]->toArray());
        $caster = new Caster(new ObjectFactoryRegistry([ExampleGreeting::class => new ExampleGreetingDeserializer()]));
        $greetings = $caster->cast(['first' => 'text', 'second' => null], 'array_of_object', ExampleGreeting::class . '[]');
        $this->assertIsArray($greetings);
        $this->assertInstanceOf(ExampleGreeting::class, $greetings['first']);
        $this->assertSame('text', $greetings['first']->getMessage());
        $this->assertNull($greetings['second']);
        $this->assertSame([], $this->caster->cast([], 'object', 'array|string'));
    }

    public function testObjectArrayTypesKeepTheirDocumentationAndHydrationStrategiesDistinct(): void
    {
        $type = ExampleBag::class . '[]';
        $this->assertSame('array', $this->caster->resolveType($type));
        $this->assertSame('array_of_object', $this->caster->resolveCastStrategy($type));
    }

    public function testAlreadyTypedNumericValuesRemainUnchanged(): void
    {
        $this->assertSame(12, $this->caster->cast(12, 'integer', 'int'));
        $this->assertSame(2.5, $this->caster->cast(2.5, 'number', 'float'));
    }

    public function testBackedEnumsResolveToTheTypeOfTheirBackingValues(): void
    {
        $this->assertSame('string', $this->caster->resolveType(ExampleBackedEnum::class));
        $this->assertSame('integer', $this->caster->resolveType(ExampleIntBackedEnum::class));
    }

    public function testEnumsWithoutBackingValuesResolveAsObjects(): void
    {
        $this->assertSame('object', $this->caster->resolveType(ExamplePureEnum::class));
        $this->assertSame('object', $this->caster->resolveCastStrategy(ExamplePureEnum::class));
    }

    public function testBackedEnumsHaveTheirOwnCastStrategy(): void
    {
        $this->assertSame('enum', $this->caster->resolveCastStrategy(ExampleBackedEnum::class));
        $this->assertSame('enum', $this->caster->resolveCastStrategy(ExampleIntBackedEnum::class));
    }

    public function testUnionsWithAnEnumKeepTheObjectStrategy(): void
    {
        $this->assertSame('object', $this->caster->resolveCastStrategy(ExampleBackedEnum::class . '|string'));
    }

    public function testCastsBackingValuesToEnumCases(): void
    {
        $this->assertSame(ExampleBackedEnum::Inactive, $this->caster->cast('inactive', 'enum', ExampleBackedEnum::class));
        $this->assertSame(ExampleIntBackedEnum::High, $this->caster->cast(2, 'enum', ExampleIntBackedEnum::class));
    }

    public function testAnEnumCaseIsKept(): void
    {
        $this->assertSame(
            ExampleBackedEnum::Active,
            $this->caster->cast(ExampleBackedEnum::Active, 'enum', ExampleBackedEnum::class)
        );
    }

    public function testNullIsNotCastToAnEnum(): void
    {
        $this->assertNull($this->caster->cast(null, 'enum', ExampleBackedEnum::class));
    }

    #[TestWith(['deleted', ExampleBackedEnum::class, 'The value "deleted" is not valid for the enum ' . ExampleBackedEnum::class . '. The allowed values are: "active", "inactive".'])]
    #[TestWith([3, ExampleIntBackedEnum::class, 'The value 3 is not valid for the enum ' . ExampleIntBackedEnum::class . '. The allowed values are: 1, 2.'])]
    #[TestWith(['1', ExampleIntBackedEnum::class, 'The value "1" is not valid for the enum ' . ExampleIntBackedEnum::class . '. The allowed values are: 1, 2.'])]
    #[TestWith([1, ExampleBackedEnum::class, 'The value 1 is not valid for the enum ' . ExampleBackedEnum::class . '. The allowed values are: "active", "inactive".'])]
    #[TestWith([['active'], ExampleBackedEnum::class, 'The value array is not valid for the enum ' . ExampleBackedEnum::class . '. The allowed values are: "active", "inactive".'])]
    #[TestWith([1.5, ExampleIntBackedEnum::class, 'The value float is not valid for the enum ' . ExampleIntBackedEnum::class . '. The allowed values are: 1, 2.'])]
    public function testInvalidEnumValuesAreRejectedListingTheAllowedValues(mixed $value, string $enum, string $message): void
    {
        try {
            $this->caster->cast($value, 'enum', $enum);
            $this->fail('The value must be rejected.');
        } catch (InvalidEnumValueException $exception) {
            $this->assertSame($message, $exception->getMessage());
            $this->assertNotNull($exception->getPrevious());
        }
    }

    #[TestWith([ExamplePureEnum::class])]
    #[TestWith([stdClass::class])]
    public function testTheEnumStrategyRejectsATypeThatIsNotABackedEnum(string $type): void
    {
        $this->expectException(InvalidParameterTypeException::class);

        $this->caster->cast('active', 'enum', $type);
    }
}
