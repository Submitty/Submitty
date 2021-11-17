<?php

declare(strict_types=1);

namespace tests\app\libraries\database;

use app\libraries\database\DatabaseUtils;

class DatabaseUtilsTester extends \PHPUnit\Framework\TestCase {
    public function formatQueryProvider(): array {
        return [
            ['SELECT * FROM foo', [], 'SELECT * FROM foo'],
            ['SELECT * FROM foo WHERE id = ?', [1], 'SELECT * FROM foo WHERE id = 1'],
            ['SELECT * FROM foo WHERE id IN (?,?,?)', [1,2,3], 'SELECT * FROM foo WHERE id IN (1,2,3)'],
            ['SELECT * FROM foo WHERE id = ?', ['test'], "SELECT * FROM foo WHERE id = 'test'"],
        ];
    }

    /**
     * @dataProvider formatQueryProvider
     */
    public function testFormatQuery(string $sql, array $params, string $expected): void {
        $this->assertEquals($expected, DatabaseUtils::formatQuery($sql, $params));
    }
}
