<?php

namespace tests\integrationTests\lib;

use \lib\Database;
use lib\ExceptionHandler;
use lib\ServerException;

class DatabaseTester extends \PHPUnit_Framework_TestCase {

    private static $tables = array();

    public static function setUpBeforeClass() {
        Database::disconnect();
        ExceptionHandler::$debug = false;
    }

    /*
     * We leave a database connection available for later tests (outside the lib folder) that require it. Not best
     * test design, but Database does not need to be anything but a static class with only one connection.
     */
    public static function tearDownAfterClass() {
        DatabaseTester::connect();
        foreach (DatabaseTester::$tables as $table) {
            Database::query("DROP TABLE {$table}");
        }
    }

    public static function connect() {
        Database::connect(__DATABASE_HOST__, __DATABASE_USER__, __DATABASE_PASSWORD__, __DATABASE_NAME__);
    }

    public static function disconnect() {
        Database::disconnect();
    }

    public function testSingleton() {
        $db = Database::getInstance();
        $this->assertTrue($db === Database::getInstance());

        $d = new \ReflectionClass("\\lib\\Database");
        $this->assertTrue($d->getMethod("__clone")->isPrivate());
        $this->assertTrue($d->getMethod("__construct")->isPrivate());
    }

    public function testConnect() {
        Database::connect(__DATABASE_HOST__, __DATABASE_USER__, __DATABASE_PASSWORD__, __DATABASE_NAME__);
        $this->assertTrue(Database::hasConnection());
        Database::disconnect();
        $this->assertFalse(Database::hasConnection());
    }

    /**
     * @expectedException \lib\ServerException
     */
    public function testBadConnect() {
        Database::connect("","","","");
    }

    public function testQuery() {
        DatabaseTester::connect();
        Database::query("SELECT user_id FROM users WHERE user_id='pevelm'");
        $this->assertEquals(1, count(Database::rows()));
        $this->assertEquals(array('user_id' => 'pevelm', '0' => 'pevelm'), Database::row());
        DatabaseTester::disconnect();
    }

    /**
     * @expectedException \lib\ServerException
     */
    public function testQueryBad() {
        DatabaseTester::connect();
        Database::query("SELECT user_id FROM bad_table");
        DatabaseTester::disconnect();
    }

    public function testEmptyResult() {
        DatabaseTester::connect();
        Database::query("SELECT * FROM users WHERE user_id='nonstudent'");
        $this->assertEquals(0, count(Database::rows()));
        $this->assertEquals(array(), Database::row());
        DatabaseTester::disconnect();
    }

    public function testQueryCount() {
        DatabaseTester::connect();
        for ($i = 0; $i < 10; $i++) {
            Database::query("SELECT * FROM users LIMIT 1");
        }
        $this->assertEquals(10, Database::totalQueries());
        DatabaseTester::disconnect();
    }

    public function testPrintQueries() {
        DatabaseTester::connect();
        Database::query("SELECT * FROM users");
        Database::query("SELECT * FROM users WHERE user_id=?", array('pevelm'));
        $this->assertEquals("1) SELECT * FROM users<br />---<br />2) SELECT * FROM users WHERE user_id='pevelm'<br />---<br />",
                            Database::getQueries());
        DatabaseTester::disconnect();
    }

    public function testGoodTransaction() {
        DatabaseTester::connect();
        $table_name = uniqid("dbtrans_");
        DatabaseTester::$tables[] = $table_name;
        Database::query("CREATE TABLE {$table_name} (field_1 int)");
        Database::beginTransaction();
        $this->assertTrue(Database::inTransaction());
        Database::query("INSERT INTO {$table_name} VALUES (?)", array(1));
        Database::query("INSERT INTO {$table_name} VALUES (?)", array(2));
        Database::commit();
        $this->assertFalse(Database::inTransaction());
        Database::query("SELECT * FROM {$table_name} ORDER BY field_1 ASC");
        $rows = Database::rows();
        $this->assertCount(2, $rows);
        $this->assertEquals(1, $rows[0]['field_1']);
        $this->assertEquals(2, $rows[1]['field_1']);
        DatabaseTester::disconnect();
    }

    public function testBadTransaction() {
        DatabaseTester::connect();
        $table_name = uniqid("dbtrans_");
        DatabaseTester::$tables[] = $table_name;
        Database::query("CREATE TABLE {$table_name} (field_1 int)");
        try {
            Database::beginTransaction();
            $this->assertTrue(Database::inTransaction());
            Database::query("INSERT INTO {$table_name} VALUES (?)", array('aaa'));
            $this->fail("Should have thrown exception");
        }
        catch (ServerException $e) {
            $this->assertFalse(Database::inTransaction());
            Database::query("SELECT * FROM {$table_name}");
            $this->assertEmpty(Database::rows());
        }
        catch (\Exception $e) {
            $this->fail("Wrong exception was thrown");
        }
        DatabaseTester::disconnect();
    }
}