<?php

namespace tests\app\libraries;

use \app\libraries\FileUtils;
use \app\libraries\Utils;

class FileUtilsTester extends \PHPUnit\Framework\TestCase {

    private $path;

    public function setUp(): void {
        $this->path = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
    }

    public function tearDown(): void {
        if (file_exists($this->path)) {
            $this->assertTrue(FileUtils::recursiveRmdir($this->path), "Could not clean up {$this->path}");
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

    public function testRecursiveFlattenImageCopy() {
        FileUtils::createDir($this->path);
        $src = FileUtils::joinPaths($this->path, 'src');
        $sub = FileUtils::joinPaths($this->path, 'src', 'sub');
        FileUtils::createDir($src);
        FileUtils::createDir($sub);
        FileUtils::createDir(FileUtils::joinPaths($src, '.git'));
        $test_images = FileUtils::joinPaths(__TEST_DATA__, 'images');
        foreach (['jpg', 'jpeg', 'gif', 'png'] as $ext) {
            copy(
                FileUtils::joinPaths($test_images, "test_image.{$ext}"),
                FileUtils::joinPaths($src, "TeSt_ImAgE.{$ext}")
            );
            copy(
                FileUtils::joinPaths($test_images, "test_image.{$ext}"),
                FileUtils::joinPaths($sub, "TeSt_ImAgE_2.{$ext}")
            );
        }
        copy(FileUtils::joinPaths($test_images, 'test_image.jpg'), FileUtils::joinPaths($src, '.git', 'test_image_3.jpg'));
        copy(FileUtils::joinPaths(__TEST_DATA__, 'test.txt'), FileUtils::joinPaths($src, 'test.txt'));
        $dst = FileUtils::joinPaths($this->path, 'dst');
        FileUtils::createDir($dst);
        FileUtils::recursiveFlattenImageCopy($src, $dst);
        $expected = [
            "test_image.gif" => [
                "name" => "test_image.gif",
                "path" => FileUtils::joinPaths($dst, "test_image.gif"),
                "size" => 10041,
                "relative_name" => "test_image.gif"
            ],
            "test_image.jpeg" => [
                "name" => "test_image.jpeg",
                "path" => FileUtils::joinPaths($dst, "test_image.jpeg"),
                "size" => 14040,
                "relative_name" => "test_image.jpeg"
            ],
            "test_image.jpg" => [
                "name" => "test_image.jpg",
                "path" => FileUtils::joinPaths($dst, "test_image.jpg"),
                "size" => 767,
                "relative_name" => "test_image.jpg"
            ],
            "test_image.png" => [
                "name" => "test_image.png",
                "path" => FileUtils::joinPaths($dst, "test_image.png"),
                "size" => 3440,
                "relative_name" => "test_image.png"
            ],
            "test_image_2.gif" => [
                "name" => "test_image_2.gif",
                "path" => FileUtils::joinPaths($dst, "test_image_2.gif"),
                "size" => 10041,
                "relative_name" => "test_image_2.gif"
            ],
            "test_image_2.jpeg" => [
                "name" => "test_image_2.jpeg",
                "path" => FileUtils::joinPaths($dst, "test_image_2.jpeg"),
                "size" => 14040,
                "relative_name" => "test_image_2.jpeg"
            ],
            "test_image_2.jpg" => [
                "name" => "test_image_2.jpg",
                "path" => FileUtils::joinPaths($dst, "test_image_2.jpg"),
                "size" => 767,
                "relative_name" => "test_image_2.jpg"
            ],
            "test_image_2.png" => [
                "name" => "test_image_2.png",
                "path" => FileUtils::joinPaths($dst, "test_image_2.png"),
                "size" => 3440,
                "relative_name" => "test_image_2.png"
            ]
        ];
        $this->assertEquals($expected, FileUtils::getAllFiles($dst, [], true));
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
        $this->assertFalse(FileUtils::isValidFileName("file\\"));
        $this->assertFalse(FileUtils::isValidFileName(0));
    }

    public function validImageProvider() {
        return [
            [FileUtils::joinPaths(__TEST_DATA__, 'images', 'test_image.gif'), true],
            [FileUtils::joinPaths(__TEST_DATA__, 'images', 'test_image.jpeg'), true],
            [FileUtils::joinPaths(__TEST_DATA__, 'images', 'test_image.jpg'), true],
            [FileUtils::joinPaths(__TEST_DATA__, 'images', 'test_image.png'), true],
            [FileUtils::joinPaths(__TEST_DATA__, 'test.txt'), false],
        ];
    }

    /**
     * @dataProvider validImageProvider
     */
    public function testIsValidImage($path, $expected) {
        $this->assertSame($expected, FileUtils::isValidImage($path));
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

    public function fileExtensions() {
        return array(
            array('test.pdf', 'application/pdf'),
            array('test.png', 'image/png'),
            array('test.jpg', 'image/jpeg'),
            array('test.jpeg', 'image/jpeg'),
            array('test.gif', 'image/gif'),
            array('test.bmp', 'image/bmp'),
            array('test.c', 'text/x-csrc'),
            array('test.cpp', 'text/x-c++src'),
            array('test.cxx', 'text/x-c++src'),
            array('test.h', 'text/x-c++src'),
            array('test.hpp', 'text/x-c++src'),
            array('test.hxx', 'text/x-c++src'),
            array('test.java', 'text/x-java'),
            array('test.py', 'text/x-python'),
            array('test.sh', 'text/x-sh'),
            array('test', 'text/x-sh'),
            array(null, null)
        );
    }

    /**
     * @dataProvider fileExtensions
     * @param $filename
     * @param $expected
     */
    public function testContentType($filename, $expected) {
        $this->assertEquals($expected, FileUtils::getContentType($filename));
    }

    public function testGetAllDirs() {
        $base_dir = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
        FileUtils::createDir($base_dir);
        $folders = array();
        for ($i = 0; $i < 10; $i++) {
            FileUtils::createDir(FileUtils::joinPaths($base_dir, "folder{$i}"));
            $folders[] = "folder{$i}";
        }
        FileUtils::createDir(FileUtils::joinPaths($base_dir, ".git"));
        $dirs = FileUtils::getAllDirs($base_dir);
        sort($folders);
        sort($dirs);
        $this->assertEquals($folders, $dirs);
        FileUtils::recursiveRmdir($base_dir);
    }

    private function buildFakeFile($fd, $filename, $part = 1, $err = 0, $target_size = 100) {
        fseek($fd, $target_size-1,SEEK_CUR); 
        fwrite($fd,'a'); 
        fclose($fd);

        $_FILES["files{$part}"]['name'][] = $filename;
        $_FILES["files{$part}"]['type'][] = FileUtils::getMimeType($this->path . $filename);
        $_FILES["files{$part}"]['size'][] = filesize($this->path . $filename);

        $tmpname = $this->path . Utils::generateRandomString() . $filename;
        copy($this->path . $filename, $tmpname);

        $_FILES["files{$part}"]['tmp_name'][] = $tmpname;
        $_FILES["files{$part}"]['error'][] = $err;

    }

    public function testvalidateUploadedFilesGood(){
        $name = "foo.txt";
        $tmpfile = fopen($this->path . $name, "w");
        $this->buildFakeFile($tmpfile, $name);

        $name = "foo2.txt";
        $tmpfile2 = fopen($this->path . $name, "w");
        $this->buildFakeFile($tmpfile2, $name);

        $stat = FileUtils::validateUploadedFiles($_FILES["files1"]);

        $this->assertCount(2, $stat );
        $this->assertEquals($stat[0], 
            ['name' => 'foo.txt',
             'type' => 'application/octet-stream',
             'error' => 'No error.',
             'size' => 100,
             'success' => true
            ]);

          $this->assertEquals($stat[1], 
            ['name' => 'foo2.txt',
             'type' => 'application/octet-stream',
             'error' => 'No error.',
             'size' => 100,
             'success' => true
            ]);
    }

    public function testvalidateUploadedFilesBad(){
        $name = "bad.txt";

        $tmpfile = fopen($this->path . $name, "w");
        $this->buildFakeFile($tmpfile, $name,2,3);

        $stat = FileUtils::validateUploadedFiles($_FILES["files2"]);

        $this->assertCount(1, $stat);
        $this->assertEquals($stat[0], 
            ['name' => 'bad.txt',
             'type' => 'application/octet-stream',
             'error'=> 'The file was only partially uploaded',
             'size' => 100,
             'success'=> false
             ]
        );

        $name = "bad2.txt";
        $tmpfile = fopen($this->path . $name, "w");
        $this->buildFakeFile($tmpfile, $name,2,4);

        $stat = FileUtils::validateUploadedFiles($_FILES["files2"]);

        $this->assertCount(2, $stat);
        $this->assertEquals($stat[1], 
            ['name' => 'bad2.txt',
             'type' => 'application/octet-stream',
             'error'=> 'No file was uploaded.',
             'size' => 100,
             'success'=> false
             ]
        );

        $name = "bad3.txt";
        $tmpfile = fopen($this->path . $name, "w");
        $this->buildFakeFile($tmpfile, $name, 2,5);

        $name = "bad3.txt";
        $tmpfile = fopen($this->path . $name, "w");
        $this->buildFakeFile($tmpfile, $name, 2,6);

        $name = "bad3.txt";
        $tmpfile = fopen($this->path . $name, "w");
        $this->buildFakeFile($tmpfile, $name, 2,7);

        $name = "bad3.txt";
        $tmpfile = fopen($this->path . $name, "w");
        $this->buildFakeFile($tmpfile, $name, 2,8);

        $stat = FileUtils::validateUploadedFiles($_FILES["files2"]);

        $this->assertCount(6, $stat);
        $this->assertEquals($stat[1], 
            ['name' => 'bad2.txt',
             'type' => 'application/octet-stream',
             'error'=> 'No file was uploaded.',
             'size' => 100,
             'success'=> false
             ]
        );

        $this->assertEquals($stat[2], 
            ['name' => 'bad3.txt',
             'type' => 'application/octet-stream',
             'error'=> 'Unknown error code.',
             'size' => 100,
             'success'=> false
             ]
        );

        $name = "\?<>.txt";
        $tmpfile = fopen($this->path . $name, "w");
        $this->buildFakeFile($tmpfile, $name, 2,0);

        $stat = FileUtils::validateUploadedFiles($_FILES["files2"]);

        $this->assertCount(7, $stat);
        $this->assertEquals($stat[6], 
            ['name' => '\?<>.txt',
             'type' => 'application/octet-stream',
             'error'=> 'Invalid filename',
             'size' => 100,
             'success'=> false
             ]
        );
    }

    public function testvalidateUploadedFilesBig(){
        $name = "big.txt";

        $tmpfile = fopen($this->path . $name, "w");
        $this->buildFakeFile($tmpfile, $name, 3, 0, 100+ Utils::returnBytes(ini_get('upload_max_filesize')));

        $stat = FileUtils::validateUploadedFiles($_FILES["files3"]);

        $this->assertCount(1, $stat);
        $this->assertEquals($stat[0], 
            ['name' => 'big.txt',
             'type' => 'application/octet-stream',
             'error'=> 'File "big.txt" too large got (2.0000953674316MB)',
             'size' => 100+ Utils::returnBytes(ini_get('upload_max_filesize')),
             'success'=> false
             ]
        );

        $name = "just_big_enough.txt";
        $tmpfile = fopen($this->path . $name, "w");
        $this->buildFakeFile($tmpfile, $name,3, 0, Utils::returnBytes(ini_get('upload_max_filesize')));

        $stat = FileUtils::validateUploadedFiles($_FILES["files3"]);

        $this->assertCount(2, $stat);
        $this->assertEquals($stat[1], 
            ['name' => 'just_big_enough.txt',
             'type' => 'application/octet-stream',
             'error'=> 'No error.',
             'size' =>  Utils::returnBytes(ini_get('upload_max_filesize')),
             'success'=> true
             ]
        );
    }

    public function testvalidateUploadedFilesFail(){
        $stat = FileUtils::validateUploadedFiles(null);
        $this->assertArrayHasKey("failed",$stat);
        $this->assertEquals($stat["failed"], "No files sent to validate" );

        $stat = FileUtils::validateUploadedFiles([]);
        $this->assertArrayHasKey("failed",$stat);
        $this->assertEquals($stat["failed"], "No files sent to validate" );
    }
}
