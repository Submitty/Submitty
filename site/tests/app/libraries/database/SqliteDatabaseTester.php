<?php

namespace tests\app\libraries\database;

use app\libraries\database\SqliteDatabase;

class SqliteDatabaseTester extends \PHPUnit\Framework\TestCase {
    public function testMemorySqliteDSN() {
        $database = new SqliteDatabase(['memory' => true]);
        $this->assertEquals(
            ['memory' => true, 'driver' => 'pdo_sqlite'],
            $database->getConnectionDetails()
        );
    }

    public function testPathSqliteDSN() {
        $database = new SqliteDatabase(['path' => '/tmp/test.sq3']);
        $this->assertEquals(
            ['memory' => false, 'path' => '/tmp/test.sq3', 'driver' => 'pdo_sqlite'],
            $database->getConnectionDetails()
        );
    }

    public function testFromDatabaseToPHPArray() {
        $database = new SqliteDatabase();
        $this->expectException(\app\exceptions\NotImplementedException::class);
        $database->fromDatabaseToPHPArray("");
    }

    public function testFromPHPToDatabaseArray() {
        $database = new SqliteDatabase();
        $this->expectException(\app\exceptions\NotImplementedException::class);
        $database->fromPHPToDatabaseArray([]);
    }
}
