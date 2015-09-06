<?php

namespace tests\lib;

use \lib\FileUtils;

class FileUtilsTester extends \PHPUnit_Framework_TestCase {
    public static function setUpBeforeClass() {
        $class = new \ReflectionClass("\\tests\\lib\\FileUtilsTester");
        for ($i = 0; $i < count($class->getMethods())-1; $i++) {
            FileUtils::recursiveRmdir(__DIR__."/FileUtilsTest".$i);
        }
    }
    
    public function testCreateNonExistantDir() {
        $this->assertFileNotExists(__DIR__."/FileUtilsTest1");
        $this->assertTrue(FileUtils::createDir(__DIR__."/FileUtilsTest1"));
        $this->assertFileExists(__DIR__."/FileUtilsTest1");
        $this->assertTrue(is_dir(__DIR__."/FileUtilsTest1"));
        FileUtils::recursiveRmdir(__DIR__."/FileUtilsTest1");
    }
    
    public function testCreateExistingDir() {
        $this->assertFileNotExists(__DIR__."/FileUtilsTest2");
        $this->assertTrue(FileUtils::createDir(__DIR__."/FileUtilsTest2"));
        $this->assertTrue(FileUtils::createDir(__DIR__."/FileUtilsTest2"));
        $this->assertFileExists(__DIR__."/FileUtilsTest2");
        $this->assertTrue(is_dir(__DIR__."/FileUtilsTest2"));
        FileUtils::recursiveRmdir(__DIR__."/FileUtilsTest2");
    }
    
    public function testCreateDirOverFile() {
        $this->assertFileNotExists(__DIR__."/FileUtilsTest3");
        file_put_contents(__DIR__."/FileUtilsTest3", "Some Data");
        $this->assertFileExists(__DIR__."/FileUtilsTest3");
        $this->assertFalse(is_dir(__DIR__."/FileUtilsTest3"));
        $this->assertTrue(FileUtils::createDir(__DIR__."/FileUtilsTest3"));
        $this->assertFileExists(__DIR__."/FileUtilsTest3");
        $this->assertTrue(is_dir(__DIR__."/FileUtilsTest3"));
        FileUtils::recursiveRmdir(__DIR__."/FileUtilsTest3");
    }
    
    public function testRecursiveRmDir() {
        $this->assertFileNotExists(__DIR__."/FileUtilsTest4");
        FileUtils::createDir(__DIR__."/FileUtilsTest4");
        file_put_contents(__DIR__."/FileUtilsTest4/test.txt", "a");
        file_put_contents(__DIR__."/FileUtilsTest4/test2.txt", "b");
        FileUtils::createDir(__DIR__."/FileUtilsTest4/a");
        FileUtils::createDir(__DIR__."/FileUtilsTest4/b");
        file_put_contents(__DIR__."/FileUtilsTest4/b/test.txt", "aa");
        FileUtils::recursiveRmdir(__DIR__."/FileUtilsTest4");
        $this->assertFileNotExists(__DIR__."/FileUtilsTest4");
    }
    
    public function testEmptyDir() {
        $this->assertFileNotExists(__DIR__."/FileUtilsTest5");
        FileUtils::createDir(__DIR__."/FileUtilsTest5");
        file_put_contents(__DIR__."/FileUtilsTest5/test.txt", "a");
        file_put_contents(__DIR__."/FileUtilsTest5/test2.txt", "b");
        FileUtils::createDir(__DIR__."/FileUtilsTest5/a");
        FileUtils::createDir(__DIR__."/FileUtilsTest5/b");
        file_put_contents(__DIR__."/FileUtilsTest5/b/test.txt", "aa");
        FileUtils::emptyDir(__DIR__."/FileUtilsTest5");
        $this->assertFileExists(__DIR__."/FileUtilsTest5");
        $this->assertCount(2,scandir(__DIR__."/FileUtilsTest5"));
        FileUtils::recursiveRmdir(__DIR__."/FileUtilsTest5");
    }
}
