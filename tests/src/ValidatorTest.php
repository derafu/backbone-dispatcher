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

use Derafu\BackboneDispatcher\Service\Resolution\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(Validator::class)]
class ValidatorTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator();
    }

    #[TestWith([1, 'int', true])]
    #[TestWith(['1', 'int', false])]
    #[TestWith(['a', 'string', true])]
    #[TestWith([1, 'string', false])]
    #[TestWith([true, 'bool', true])]
    #[TestWith([1, 'bool', false])]
    #[TestWith([['a', 'b'], 'array', true])]
    #[TestWith(['a', 'array', false])]
    public function testValidatesKnownTypes(mixed $value, string $type, bool $expected): void
    {
        $this->assertSame($expected, $this->validator->validate($value, $type));
    }

    public function testUnknownTypesAreAlwaysConsideredValid(): void
    {
        $this->assertTrue($this->validator->validate('anything', 'SomeInterface\That\Does\Not\Exist'));
        $this->assertTrue($this->validator->validate(null, 'SomeInterface\That\Does\Not\Exist'));
        $this->assertTrue($this->validator->validate(new \stdClass(), 'object'));
    }
}
