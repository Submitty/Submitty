<?php

namespace tests\app\libraries\database;

use app\libraries\database\SqliteDatabase;

class SqliteDatabaseTester extends \PHPUnit\Framework\TestCase {
    function testMemorySqliteDSN() {
        $database = new SqliteDatabase(array('memory' => true));
        $this->assertEquals("sqlite::memory:", $database->getDSN());
    }

    public function testPathSqliteDSN() {
        $database = new SqliteDatabase(array('path' => '/tmp/test.sq3'));
        $this->assertEquals("sqlite:/tmp/test.sq3", $database->getDSN());
    }

    public function testFromDatabaseToPHPArray() {
        $database = new SqliteDatabase();
        $this->expectException(\app\exceptions\NotImplementedException::class);
        $database->fromDatabaseToPHPArray("");
    }

    public function testFromPHPToDatabaseArray() {
        $database = new SqliteDatabase();
        $this->expectException(\app\exceptions\NotImplementedException::class);
        $database->fromPHPToDatabaseArray(array());
    }
}
