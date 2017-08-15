<?php

namespace unitTests\app\libraries\database;

use app\libraries\database\SqliteDatabase;

class DatabaseTester extends \PHPUnit_Framework_TestCase {
    public function testSqliteDatabaseMemory() {
        $database = new SqliteDatabase(array('memory' => true));
        $database->connect();
        $database->query("CREATE TABLE test(pid integer PRIMARY KEY, tcol text NOT NULL)");
        $database->query("INSERT INTO test VALUES (?, ?)", array(1, 'a'));
        $database->query("INSERT INTO test VALUES (?, ?)", array(2, 'b'));

        $this->assertNotEmpty($database->getQueries());
        $database->disconnect();
    }
}
