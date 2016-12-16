<?php

namespace tests\unitTests\app\libraries;

use \app\libraries\FileUtils;
use \app\libraries\Utils;

class FileUtilsTester extends \PHPUnit_Framework_TestCase {

    private $path;

    public function setUp() {
        $this->path = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
    }

    public function tearDown() {
        if (file_exists($this->path)) {
            FileUtils::recursiveRmdir($this->path);
        }
    }

    public function testCreateNonExistantDir() {
        $this->assertFileNotExists($this->path);
        $this->assertTrue(FileUtils::createDir($this->path));
        $this->assertFileExists($this->path);
        $this->assertTrue(is_dir($this->path));
    }

    public function testCreateExistingDir() {
        $this->assertFileNotExists($this->path);
        $this->assertTrue(FileUtils::createDir($this->path));
        $this->assertTrue(FileUtils::createDir($this->path));
        $this->assertFileExists($this->path);
        $this->assertTrue(is_dir($this->path));
    }

    public function testCreateDirOverFile() {
        $this->assertFileNotExists($this->path);
        file_put_contents($this->path, "Some Data");
        $this->assertFileExists($this->path);
        $this->assertFalse(is_dir($this->path));
        $this->assertTrue(FileUtils::createDir($this->path));
        $this->assertFileExists($this->path);
        $this->assertTrue(is_dir($this->path));
    }

    public function testRecursiveRmDir() {
        $this->assertFileNotExists($this->path);
        FileUtils::createDir($this->path);
        file_put_contents(FileUtils::joinPaths($this->path, "test.txt"), "a");
        file_put_contents(FileUtils::joinPaths($this->path, "test2.txt"), "b");
        FileUtils::createDir(FileUtils::joinPaths($this->path, "a"));
        FileUtils::createDir(FileUtils::joinPaths($this->path, "b"));
        file_put_contents(FileUtils::joinPaths($this->path, "b", "test.txt"), "aa");
        FileUtils::recursiveRmdir($this->path);
        $this->assertFileNotExists($this->path);
    }

    public function testEmptyDir() {
        $this->assertFileNotExists($this->path);
        FileUtils::createDir($this->path);
        file_put_contents(FileUtils::joinPaths($this->path, "test.txt"), "a");
        file_put_contents(FileUtils::joinPaths($this->path, "test.txt"), "b");
        FileUtils::createDir(FileUtils::joinPaths($this->path, "a"));
        FileUtils::createDir(FileUtils::joinPaths($this->path, "b"));
        file_put_contents(FileUtils::joinPaths($this->path, "b", "test.txt"), "aa");
        FileUtils::emptyDir($this->path);
        $this->assertFileExists($this->path);
        $this->assertCount(2,scandir($this->path));
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
