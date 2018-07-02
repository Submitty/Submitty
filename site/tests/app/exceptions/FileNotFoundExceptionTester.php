<?php


namespace tests\app\exceptions;


use app\exceptions\FileNotFoundException;

class FileNotFoundExceptionTester extends \PHPUnit\Framework\TestCase {
    public function testFileNotFoundMessage() {
        try {
            throw new FileNotFoundException("exception");
        }
        catch (FileNotFoundException $exc) {
            $this->assertEquals("exception", $exc->getMessage());
            $this->assertNull($exc->getDetails()['path']);
        }
    }

    public function testFileNotFoundNoMessageNoPath() {
        try {
            throw new FileNotFoundException(null, null);
        }
        catch (FileNotFoundException $exc) {
            $this->assertEquals("File could not be found", $exc->getMessage());
            $this->assertNull($exc->getDetails()['path']);
        }
    }

    public function testFileNotFoundNoMessagePath() {
        try {
            throw new FileNotFoundException(null, "test.txt");
        }
        catch (FileNotFoundException $exc) {
            $this->assertEquals("File 'test.txt' could not be found", $exc->getMessage());
            $this->assertEquals("test.txt", $exc->getDetails()['path']);
        }
    }
}
