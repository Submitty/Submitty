<?php

declare(strict_types=1);

namespace tests\app\entities\db;

use app\entities\db\Column;
use PHPUnit\Framework\TestCase;

class ColumnTester extends TestCase {
    public function testConstructorThrowsException(): void {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot create new information_schema.column');
        new Column();
    }
}
