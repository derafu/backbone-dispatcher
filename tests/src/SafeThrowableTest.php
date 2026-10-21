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

use Derafu\BackboneDispatcher\ValueObject\SafeThrowable;
use LogicException;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

#[CoversClass(SafeThrowable::class)]
class SafeThrowableTest extends TestCase
{
    public function testRealExceptionDetailsAndPathsArePreservedWithoutAProjectDirectory(): void
    {
        $exception = $this->createFailure('test argument');

        $safe = SafeThrowable::fromThrowable($exception);

        $this->assertSame(RuntimeException::class, $safe->getClass());
        $this->assertSame(42, $safe->getCode());
        $this->assertSame($exception->getMessage(), $safe->getMessage());
        $this->assertSame($exception->getFile(), $safe->getFile());
        $this->assertSame($exception->getLine(), $safe->getLine());
        $originalFrame = $exception->getTrace()[0];
        $safeFrame = $safe->getTrace()[0];
        $this->assertArrayHasKey('file', $originalFrame);
        $this->assertArrayHasKey('file', $safeFrame);
        $this->assertSame($originalFrame['file'] ?? null, $safeFrame['file'] ?? null);
        $this->assertNull($safe->getPrevious());
        $this->assertStringNotContainsString('Caused by:', (string) $safe);
    }

    public function testArgumentValuesAreRemovedFromEveryRealStackFrame(): void
    {
        $original = ini_get('zend.exception_ignore_args');
        $this->assertNotFalse($original);
        try {
            $this->assertNotFalse(ini_set('zend.exception_ignore_args', '0'));
            $exception = $this->createFailure('sensitive-test-value');
            $originalFrame = $exception->getTrace()[0];
            $this->assertArrayHasKey('args', $originalFrame);
            $this->assertSame(['sensitive-test-value'], $originalFrame['args'] ?? null);

            $safe = SafeThrowable::fromThrowable($exception);

            $this->assertCount(count($exception->getTrace()), $safe->getTrace());
            foreach ($safe->getTrace() as $frame) {
                $this->assertArrayNotHasKey('args', $frame);
            }
            $safeFrame = $safe->getTrace()[0];
            $this->assertArrayHasKey('function', $safeFrame);
            $this->assertSame('createFailure', $safeFrame['function'] ?? null);
            $this->assertStringNotContainsString('sensitive-test-value', json_encode($safe, JSON_THROW_ON_ERROR));
        } finally {
            ini_set('zend.exception_ignore_args', $original);
        }
    }

    public function testProjectPathsAreObfuscatedThroughoutTheExceptionChainAndJson(): void
    {
        $previous = $this->createFailure('previous argument');
        $exception = new LogicException('The operation could not complete.', 7, $previous);
        $projectDir = dirname(__DIR__, 2);

        $safe = SafeThrowable::fromThrowable($exception, $projectDir);

        $this->assertSame('project_dir:tests/src/SafeThrowableTest.php', $safe->getFile());
        $cause = $safe->getPrevious();
        $this->assertNotNull($cause);
        $this->assertSame(RuntimeException::class, $cause->getClass());
        $this->assertSame(42, $cause->getCode());
        $this->assertSame('project_dir:tests/src/SafeThrowableTest.php', $cause->getFile());
        $this->assertNull($cause->getPrevious());
        $causeFrame = $cause->getTrace()[0];
        $this->assertArrayHasKey('file', $causeFrame);
        $this->assertStringStartsWith('project_dir:', $causeFrame['file'] ?? '');
        $this->assertSame($safe->toArray(), $safe->jsonSerialize());
        $json = json_encode($safe, JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('The operation could not complete.', $json);
        $this->assertStringContainsString('Operation failed.', $json);
        $this->assertStringNotContainsString($projectDir . '/', $json);
        $rendered = (string) $safe;
        $this->assertStringContainsString('Class: LogicException', $rendered);
        $this->assertStringContainsString('Caused by:', $rendered);
        $this->assertStringContainsString('Class: RuntimeException', $rendered);
        $this->assertStringNotContainsString($projectDir . '/', $rendered);
    }

    public function testPathsOutsideTheProjectDirectoryRemainUnchanged(): void
    {
        $exception = $this->createFailure('test argument');

        $safe = SafeThrowable::fromThrowable($exception, __DIR__ . '/another-project');

        $this->assertSame($exception->getFile(), $safe->getFile());
        $originalFrame = $exception->getTrace()[0];
        $safeFrame = $safe->getTrace()[0];
        $this->assertArrayHasKey('file', $originalFrame);
        $this->assertArrayHasKey('file', $safeFrame);
        $this->assertSame($originalFrame['file'] ?? null, $safeFrame['file'] ?? null);
    }

    public function testManualFramesPreserveOptionalFieldsAndTheirOriginalIndexes(): void
    {
        $object = new stdClass();
        $trace = [
            3 => ['class' => 'ExampleService', 'type' => '::', 'function' => 'run', 'args' => ['named' => ['nested' => null]], 'object' => $object],
            8 => [],
        ];
        $safe = new SafeThrowable(RuntimeException::class, 7, 'Manual snapshot.', 'worker.php', 12, $trace, null);

        $this->assertSame($trace, $safe->getTrace());
        $this->assertSame($trace, $safe->toArray()['trace']);
        $this->assertSame("#3 [internal](?): ExampleService::run()\n#8 [internal](?): ()", $safe->getTraceAsString());
    }

    public function testAnEmptyTraceCanBeRenderedAndSerialized(): void
    {
        $safe = new SafeThrowable(RuntimeException::class, 0, 'No trace available.', 'worker.php', 12, [], null);

        $this->assertSame('', $safe->getTraceAsString());
        $this->assertSame([], $safe->getTrace());
        $this->assertSame([], $safe->jsonSerialize()['trace']);
        $this->assertStringContainsString("Stack trace:\n\n", (string) $safe);
    }

    #[RequiresPhpExtension('pdo_sqlite')]
    public function testANonnumericSqlStateIsKeptInTheMessageWithANumericFallbackCode(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        try {
            $database->query('SELECT * FROM missing_table');
            $this->fail('Querying a missing table must fail.');
        } catch (PDOException $exception) {
            $this->assertSame('HY000', $exception->getCode());

            $safe = SafeThrowable::fromThrowable($exception);

            $this->assertSame(PDOException::class, $safe->getClass());
            $this->assertSame(0, $safe->getCode());
            $this->assertSame('HY000 - ' . $exception->getMessage(), $safe->getMessage());
        }
    }

    #[RequiresPhpExtension('pdo_sqlite')]
    public function testANumericSqlStateBecomesAnIntegerWithoutChangingTheMessage(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec('CREATE TABLE items (id INTEGER PRIMARY KEY)');
        $database->exec('INSERT INTO items (id) VALUES (1)');
        try {
            $database->exec('INSERT INTO items (id) VALUES (1)');
            $this->fail('Inserting a duplicate primary key must fail.');
        } catch (PDOException $exception) {
            $this->assertSame('23000', $exception->getCode());

            $safe = SafeThrowable::fromThrowable($exception);

            $this->assertSame(23000, $safe->getCode());
            $this->assertSame($exception->getMessage(), $safe->getMessage());
        }
    }

    private function createFailure(string $argument): RuntimeException
    {
        return new RuntimeException('Operation failed.', 42);
    }
}
