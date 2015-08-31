<?php

namespace tests;

use \lib\Database;

class DatabaseTester extends \PHPUnit_Framework_TestCase {

    /*
     * We leave a database connection available for later tests (outside the lib folder) that require it. Not best
     * test design, but Database does not need to be anything but a static class with only one connection.
     */
    public static function tearDownAfterClass() {
        DatabaseTester::connect();
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
    }
    
    public function testDisconnect() {
        Database::connect(__DATABASE_HOST__, __DATABASE_USER__, __DATABASE_PASSWORD__, __DATABASE_NAME__);
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
        Database::query("SELECT student_rcs FROM students");
        $this->assertEquals(1, count(Database::rows()));
        $this->assertEquals(array('student_rcs' => 'pevelm', '0' => 'pevelm'), Database::row());
        DatabaseTester::disconnect();
    }

    /**
     * @expectedException \lib\ServerException
     */
    public function testQueryBad() {
        DatabaseTester::connect();
        Database::query("SELECT student_rcs FROM bad_table");
        DatabaseTester::disconnect();
    }
    
    public function testEmptyResult() {
        DatabaseTester::connect();
        Database::query("SELECT * FROM students WHERE student_rcs='nonstudent'");
        $this->assertEquals(0, count(Database::rows()));
        $this->assertEquals(array(), Database::row());
        DatabaseTester::disconnect();
    }
    
    public function testQueryCount() {
        DatabaseTester::connect();
        for ($i = 0; $i < 10; $i++) {
            Database::query("SELECT * FROM students LIMIT 1");
        }
        $this->assertEquals(10, Database::totalQueries());
        DatabaseTester::disconnect();
    }
    
    public function testPrintQueries() {
        DatabaseTester::connect();
        Database::query("SELECT * FROM students");
        Database::query("SELECT * FROM students WHERE student_rcs=?", array('pevelm'));
        $this->assertEquals("1) SELECT * FROM students<br />2) SELECT * FROM students WHERE student_rcs=? --- ?pevelm <br />", 
                            Database::getQueries());
        DatabaseTester::disconnect();
    }
}