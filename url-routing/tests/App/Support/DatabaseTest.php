<?php

declare(strict_types=1);

namespace Tests\App\Support;

use App\Support\Database;
use PHPUnit\Framework\TestCase;

class DatabaseTest extends TestCase
{
    public function testInsertRejectsInjectedColumnName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Database::getInstance()->insert('personas', ['nombre) VALUES (1); DROP TABLE personas; --' => 'x']);
    }

    public function testInsertRejectsInjectedTableName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Database::getInstance()->insert('personas; DROP TABLE personas', ['nombre' => 'x']);
    }

    public function testUpdateRejectsInjectedWhereColumn(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Database::getInstance()->update('personas', ['nombre' => 'x'], ['1=1 OR id' => 1]);
    }

    public function testDeleteRejectsInjectedWhereColumn(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Database::getInstance()->delete('personas', ['id = id OR 1' => 1]);
    }

    public function testListArrayKeysAreNotValidColumns(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Database::getInstance()->insert('personas', ['x', 'y']);
    }

    public function testPlainIdentifiersAreAccepted(): void
    {
        $method = new \ReflectionMethod(Database::class, 'assertIdentifiers');

        $method->invoke(Database::getInstance(), 'personas_2024', ['nombre' => 1, '_id' => 2], ['fecha_alta' => 3]);

        $this->addToAssertionCount(1);
    }
}
