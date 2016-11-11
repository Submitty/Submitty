<?php

namespace tests\unitTests\app\libraries;

use \app\libraries\FileUtils;

class FileUtilsTester extends \PHPUnit_Framework_TestCase {
    public static function setUpBeforeClass() {
        $class = new \ReflectionClass(FileUtilsTester::class);
        for ($i = 0; $i < count($class->getMethods())-1; $i++) {
            FileUtils::recursiveRmdir(__TEST_DATA__."/FileUtilsTest".$i);
        }
    }

    public function testCreateNonExistantDir() {
        $this->assertFileNotExists(__TEST_DATA__."/FileUtilsTest1");
        $this->assertTrue(FileUtils::createDir(__TEST_DATA__."/FileUtilsTest1"));
        $this->assertFileExists(__TEST_DATA__."/FileUtilsTest1");
        $this->assertTrue(is_dir(__TEST_DATA__."/FileUtilsTest1"));
        FileUtils::recursiveRmdir(__TEST_DATA__."/FileUtilsTest1");
    }

    public function testCreateExistingDir() {
        $this->assertFileNotExists(__TEST_DATA__."/FileUtilsTest2");
        $this->assertTrue(FileUtils::createDir(__TEST_DATA__."/FileUtilsTest2"));
        $this->assertTrue(FileUtils::createDir(__TEST_DATA__."/FileUtilsTest2"));
        $this->assertFileExists(__TEST_DATA__."/FileUtilsTest2");
        $this->assertTrue(is_dir(__TEST_DATA__."/FileUtilsTest2"));
        FileUtils::recursiveRmdir(__TEST_DATA__."/FileUtilsTest2");
    }

    public function testCreateDirOverFile() {
        $this->assertFileNotExists(__TEST_DATA__."/FileUtilsTest3");
        file_put_contents(__TEST_DATA__."/FileUtilsTest3", "Some Data");
        $this->assertFileExists(__TEST_DATA__."/FileUtilsTest3");
        $this->assertFalse(is_dir(__TEST_DATA__."/FileUtilsTest3"));
        $this->assertTrue(FileUtils::createDir(__TEST_DATA__."/FileUtilsTest3"));
        $this->assertFileExists(__TEST_DATA__."/FileUtilsTest3");
        $this->assertTrue(is_dir(__TEST_DATA__."/FileUtilsTest3"));
        FileUtils::recursiveRmdir(__TEST_DATA__."/FileUtilsTest3");
    }

    public function testRecursiveRmDir() {
        $this->assertFileNotExists(__TEST_DATA__."/FileUtilsTest4");
        FileUtils::createDir(__TEST_DATA__."/FileUtilsTest4");
        file_put_contents(__TEST_DATA__."/FileUtilsTest4/test.txt", "a");
        file_put_contents(__TEST_DATA__."/FileUtilsTest4/test2.txt", "b");
        FileUtils::createDir(__TEST_DATA__."/FileUtilsTest4/a");
        FileUtils::createDir(__TEST_DATA__."/FileUtilsTest4/b");
        file_put_contents(__TEST_DATA__."/FileUtilsTest4/b/test.txt", "aa");
        FileUtils::recursiveRmdir(__TEST_DATA__."/FileUtilsTest4");
        $this->assertFileNotExists(__TEST_DATA__."/FileUtilsTest4");
    }

    public function testEmptyDir() {
        $this->assertFileNotExists(__TEST_DATA__."/FileUtilsTest5");
        FileUtils::createDir(__TEST_DATA__."/FileUtilsTest5");
        file_put_contents(__TEST_DATA__."/FileUtilsTest5/test.txt", "a");
        file_put_contents(__TEST_DATA__."/FileUtilsTest5/test2.txt", "b");
        FileUtils::createDir(__TEST_DATA__."/FileUtilsTest5/a");
        FileUtils::createDir(__TEST_DATA__."/FileUtilsTest5/b");
        file_put_contents(__TEST_DATA__."/FileUtilsTest5/b/test.txt", "aa");
        FileUtils::emptyDir(__TEST_DATA__."/FileUtilsTest5");
        $this->assertFileExists(__TEST_DATA__."/FileUtilsTest5");
        $this->assertCount(2,scandir(__TEST_DATA__."/FileUtilsTest5"));
        FileUtils::recursiveRmdir(__TEST_DATA__."/FileUtilsTest5");
    }

    public function testValidFileNames() {
        $this->assertTrue(FileUtils::isValidFileName("file"));
        $this->assertFalse(FileUtils::isValidFileName("file'"));
        $this->assertFalse(FileUtils::isValidFileName("file\""));
        $this->assertFalse(FileUtils::isValidFileName("<file"));
        $this->assertFalse(FileUtils::isValidFileName("file>"));
	//$this->assertFalse(FileUtils::isValidFileName("file/"));
        $this->assertFalse(FileUtils::isValidFileName("file\\"));
        $this->assertFalse(FileUtils::isValidFileName(0));
    }

    public function joinPathsData() {
        return array(
            array("", ""),
            array("", "", ""),
            array("/", "/"),
            array("/", "", "/"),
            array("/a", "/", "a"),
            array("/a", "/", "/a"),
            array("abc/def", "abc", "def"),
            array("abc/def", "abc", "/def"),
            array("/abc/def", "/abc", "/def"),
            array("foo.jpg", "", "foo.jpg"),
            array("dir/0/a.jpg", "dir", "0", "a.jpg")
        );
    }

    /**
     * @dataProvider joinPathsData
     */
    public function testJoinPaths() {
        $args = func_get_args();
        $expected = $args[0];
        // In case we decide to test this on a non *nix system
        $rest = array_slice($args, 1);
        for ($i = 0; $i < count($rest); $i++) {
            $rest[$i] = str_replace("/", DIRECTORY_SEPARATOR, $rest[$i]);
        }
        $actual = forward_static_call_array(array('app\\libraries\\FileUtils', 'joinPaths'), array_slice($args, 1));
        $this->assertEquals($expected, $actual);
    }
}
