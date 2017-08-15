<?php

namespace tests\unitTests\app\libraries\database;

use app\libraries\database\SqliteDatabase;

class SqliteDatabaseTester extends \PHPUnit_Framework_TestCase {
    function testMemorySqliteDSN() {
        $database = new SqliteDatabase(array('memory' => true));
        $this->assertEquals("sqlite::memory:", $database->getDSN());
    }

    public function testPathSqliteDSN() {
        $database = new SqliteDatabase(array('path' => '/tmp/test.sq3'));
        $this->assertEquals("sqlite:/tmp/test.sq3", $database->getDSN());
    }
}
