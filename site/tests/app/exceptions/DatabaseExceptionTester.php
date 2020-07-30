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
            throw new DatabaseException("exception", "query", ['parameters']);
        }
        catch (DatabaseException $exc) {
            $this->assertEquals("exception", $exc->getMessage());
            $this->assertEquals(['query' => "query", "parameters" => ['parameters']], $exc->getDetails());
        }
    }
}
