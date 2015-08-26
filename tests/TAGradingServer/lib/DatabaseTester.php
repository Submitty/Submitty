<?php

namespace tests;

use \lib\Database;

class DatabaseTester extends \PHPUnit_Framework_TestCase {

    public function connect() {
        Database::connect(__DATABASE_HOST__, __DATABASE_USER__, __DATABASE_PASSWORD__, __DATABASE_NAME__);
    }
    public function disconnect() {
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
        $this->connect();
        Database::query("SELECT student_rcs FROM students");
        $this->assertEquals(1, count(Database::rows()));
        $this->assertEquals(array('student_rcs' => 'pevelm', '0' => 'pevelm'), Database::row());
        $this->disconnect();
    }

    /**
     * @expectedException \lib\ServerException
     */
    public function testQueryBad() {
        $this->connect();
        Database::query("SELECT student_rcs FROM bad_table");
        $this->disconnect();
    }
    
    public function testEmptyResult() {
        $this->connect();
        Database::query("SELECT * FROM students WHERE student_rcs='nonstudent'");
        $this->assertEquals(0, count(Database::rows()));
        $this->assertEquals(array(), Database::row());
        $this->disconnect();
    }
    
    public function testQueryCount() {
        $this->connect();
        for ($i = 0; $i < 10; $i++) {
            Database::query("SELECT * FROM students LIMIT 1");
        }
        $this->assertEquals(10, Database::totalQueries());
        $this->disconnect();
    }
    
    public function testPrintQueries() {
        $this->connect();
        Database::query("SELECT * FROM students");
        Database::query("SELECT * FROM students WHERE student_rcs=?", array('pevelm'));
        $this->assertEquals("1) SELECT * FROM students<br />2) SELECT * FROM students WHERE student_rcs=? --- ?pevelm <br />", 
                            Database::getQueries());
        $this->disconnect();
    }
}