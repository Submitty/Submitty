<?php

namespace tests\app\exceptions;

use app\exceptions\DatabaseException;

class DatabaseExceptionTester extends \PHPUnit\Framework\TestCase {
    public function testDatabaseExceptionNoQuery() {
        try {
            throw new DatabaseException("exception");
        }
        catch (DatabaseException $exc) {
            $this->assertEquals("exception", $exc->getMessage());
            $this->assertEmpty($exc->getDetails());
        }
    }

    public function testDatabaseExceptionQuery() {
        try {
            throw new DatabaseException("exception", "query", array('parameters'));
        }
        catch (DatabaseException $exc) {
            $this->assertEquals("exception", $exc->getMessage());
            $this->assertEquals(array('query' => "query", "parameters" => array('parameters')), $exc->getDetails());
        }
    }
}
