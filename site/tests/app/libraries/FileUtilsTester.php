<?php

namespace tests\app\libraries;

use app\exceptions\FileReadException;
use app\libraries\FileUtils;
use app\libraries\Utils;

class FileUtilsTester extends \PHPUnit\Framework\TestCase {
    use \phpmock\phpunit\PHPMock;

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
        $this->assertDirectoryNotExists($this->path);
        $this->assertTrue(FileUtils::createDir($this->path));
        $this->assertDirectoryExists($this->path);
    }

    public function testCreateExistingDir() {
        $this->assertDirectoryNotExists($this->path);
        $this->assertTrue(FileUtils::createDir($this->path));
        $this->assertTrue(FileUtils::createDir($this->path));
        $this->assertDirectoryExists($this->path);
    }

    public function testCreateDirOverFile() {
        $this->assertFileNotExists($this->path);
        file_put_contents($this->path, "Some Data");
        $this->assertFileExists($this->path);
        $this->assertFalse(is_dir($this->path));
        $this->assertTrue(FileUtils::createDir($this->path));
        $this->assertDirectoryExists($this->path);
    }

    public function testCreateDirRecursive() {
        $this->assertDirectoryNotExists($this->path);
        $this->assertDirectoryNotExists(FileUtils::joinPaths($this->path, 'test'));
        $this->assertTrue(FileUtils::createDir(FileUtils::joinPaths($this->path, 'test'), true));
        $this->assertDirectoryExists(FileUtils::joinPaths($this->path, 'test'));
        $this->assertDirectoryExists($this->path);
    }

    public function testCreateDirMode() {
        $this->assertDirectoryNotExists($this->path);
        $this->assertDirectoryNotExists($this->path);
        $this->assertTrue(FileUtils::createDir(FileUtils::joinPaths($this->path), false, 0777));
        $this->assertTrue(FileUtils::createDir(FileUtils::joinPaths($this->path, 'test'), false, 0555));
        $this->assertDirectoryExists($this->path);
        $this->assertSame("0777", substr(sprintf('%o', fileperms($this->path)), -4));
        $this->assertDirectoryExists(FileUtils::joinPaths($this->path, 'test'));
        $this->assertSame("0555", substr(sprintf('%o', fileperms(FileUtils::joinPaths($this->path, 'test'))), -4));
    }

    public function testRecursiveRmDir() {
        $this->assertFileNotExists($this->path);
        FileUtils::createDir($this->path);
        file_put_contents(FileUtils::joinPaths($this->path, "test.txt"), "a");
        file_put_contents(FileUtils::joinPaths($this->path, "test2.txt"), "b");
        FileUtils::createDir(FileUtils::joinPaths($this->path, "a"));
        FileUtils::createDir(FileUtils::joinPaths($this->path, "b"));
        file_put_contents(FileUtils::joinPaths($this->path, "b", "test.txt"), "aa");
        $this->assertTrue(FileUtils::recursiveRmdir($this->path));
        $this->assertFileNotExists($this->path);
    }

    /**
     * It is not possible as a normal user to make a file "undeletable", so
     * we have to mock unlink for the next two functions. However, this means
     * that tearDown will not function as expected, so have to manually clean
     * up after ourselves in the test.
     */

    /**
     * @runInSeparateProcess
     */
    public function testRecursiveRmDirFileFail() {
        FileUtils::createDir($this->path);
        file_put_contents(FileUtils::joinPaths($this->path, "test.txt"), "aa");
        $this->getFunctionMock("app\\libraries", 'unlink')
            ->expects($this->once())
            ->willReturn(false);
        $this->assertFalse(FileUtils::recursiveRmdir($this->path));
        $this->assertTrue(unlink(FileUtils::joinPaths($this->path, "test.txt")));
        $this->assertTrue(rmdir($this->path));
    }

    /**
     * @runInSeparateProcess
     */
    public function testRecursiveRmDirRecurseFail() {
        FileUtils::createDir($this->path);
        FileUtils::createDir(FileUtils::joinPaths($this->path, "a"));
        file_put_contents(FileUtils::joinPaths($this->path, "a", "test.txt"), "aa");
        $this->getFunctionMock("app\\libraries", 'unlink')
            ->expects($this->once())
            ->willReturn(false);
        $this->assertFalse(FileUtils::recursiveRmdir($this->path));
        $this->assertTrue(unlink(FileUtils::joinPaths($this->path, "a", "test.txt")));
        $this->assertTrue(rmdir(FileUtils::joinPaths($this->path, "a")));
        $this->assertTrue(rmdir($this->path));
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
        $this->assertCount(2, scandir($this->path));
    }

    public function testReadJsonFile() {
        FileUtils::createDir($this->path);
        $file = FileUtils::joinPaths($this->path, 'test.json');
        file_put_contents($file, '{"foo": true, "bar": false}');
        $this->assertEquals(['foo' => true, 'bar' => false], FileUtils::readJsonFile($file));
    }

    public function testReadJsonFileInvalidJson() {
        FileUtils::createDir($this->path);
        $file = FileUtils::joinPaths($this->path, 'test.json');
        file_put_contents($file, 'invalid {} json {} string');
        $this->assertFalse(FileUtils::readJsonFile($file));
    }

    public function testReadJsonFileNoFile() {
        $this->assertFalse(FileUtils::readJsonFile($this->path));
    }

    public function testEncodeJson() {
        $expected = <<<STRING
{
    "test": "foo",
    "foo": [
        1,
        2,
        3
    ],
    "bar": true
}
STRING;
        $obj = [
            'test' => 'foo',
            'foo' => [
                1,
                2,
                3
            ],
            'bar' => true
        ];
        $this->assertEquals($expected, FileUtils::encodeJson($obj));
        $this->assertEquals('"aaa"', FileUtils::encodeJson('aaa'));
    }

    public function testWriteJsonFile() {
        FileUtils::createDir($this->path);
        $this->assertTrue(FileUtils::writeJsonFile(FileUtils::joinPaths($this->path, 'test.json'), 'aa'));
        $this->assertStringEqualsFile(FileUtils::joinPaths($this->path, 'test.json'), '"aa"');
    }

    public function testWriteJsonFileFailEncode() {
        $data = ['a' => 1, 'b' => tmpfile()];
        $this->assertFalse(FileUtils::writeJsonFile(FileUtils::joinPaths($this->path, 'test.json'), $data));
    }

    public function testWriteJsonFileNonWritable() {
        FileUtils::createDir($this->path);
        $file = FileUtils::joinPaths($this->path, 'test.json');
        touch($file);
        try {
            chmod($file, 0400);
            $this->assertFalse(FileUtils::writeJsonFile($file, 'aa'));
        }
        finally {
            chmod($file, 0660);
        }
    }

    public function testWriteFile() {
        FileUtils::createDir($this->path);
        $file = FileUtils::joinPaths($this->path, 'test_file');
        $this->assertTrue(FileUtils::writeFile($file, "test"));
        $this->assertStringEqualsFile($file, "test");
    }

    public function testWriteFileNonWritableFile() {
        FileUtils::createDir($this->path);
        $file = FileUtils::joinPaths($this->path, 'test_file');
        touch($file);
        try {
            chmod($file, 0400);
            $this->assertFalse(FileUtils::writeFile($file, "test"));
        }
        finally {
            chmod($file, 0660);
        }
    }

    public function testGetZipSize() {
        FileUtils::createDir($this->path);
        $zip = new \ZipArchive();
        $zip->open(FileUtils::joinPaths($this->path, 'test.zip'), \ZipArchive::CREATE);
        $zip->addFromString('test', "this is a file");
        $zip->addFromString('test1', "this is a file");
        $zip->addFromString('folder/test', "this is a file");
        $zip->addFromString('folder/test2', "this is a file");
        $zip->addFromString('folder/folder/test', "this is a file");
        $zip->addFromString('folder/folder/test2', "this is a file");
        $zip->close();
        $this->assertSame(
            strlen("this is a file") * 6,
            FileUtils::getZipSize(FileUtils::joinPaths($this->path, 'test.zip'))
        );
    }

    public function testGetZipSizeNotZip() {
        $this->assertEquals(0, FileUtils::getZipSize(__TEST_DATA__));
        $this->assertEquals(0, FileUtils::getZipSize(FileUtils::joinPaths(__TEST_DATA__, 'test.txt')));
    }

    public function validFileNameProvider() {
        return [
            ['01_File.txt', true],
            ["file'.txt", false],
            ['file\".txt', false],
            ['<file', false],
            ['file>', false],
            ['file\\', false]
        ];
    }

    /**
     * @dataProvider validFileNameProvider
     */
    public function testCheckFileInZipName($filename, $expected) {
        FileUtils::createDir($this->path);
        $zip = new \ZipArchive();
        $zip->open(FileUtils::joinPaths($this->path, 'test.zip'), \ZipArchive::CREATE);
        $zip->addFromString($filename, "this is a file");
        $zip->close();
        $this->assertSame(
            $expected,
            FileUtils::checkFileInZipName(FileUtils::joinPaths($this->path, 'test.zip'))
        );
    }

    /**
     * @dataProvider validFileNameProvider
     */
    public function testValidFileName($filename, $expected) {
        $this->assertSame($expected, FileUtils::isValidFileName($filename));
    }

    public function testValidFileNameNumeric() {
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

    public function contentTypeDataProvider(): array {
        return [
            ['test.pdf', 'application/pdf'],
            ['test.png', 'image/png'],
            ['test.jpg', 'image/jpeg'],
            ['test.jpeg', 'image/jpeg'],
            ['test.gif', 'image/gif'],
            ['test.bmp', 'image/bmp'],
            ['test.c', 'text/x-csrc'],
            ['test.cpp', 'text/x-c++src'],
            ['test.cxx', 'text/x-c++src'],
            ['test.h', 'text/x-c++src'],
            ['test.hpp', 'text/x-c++src'],
            ['test.hxx', 'text/x-c++src'],
            ['test.java', 'text/x-java'],
            ['test.py', 'text/x-python'],
            ['test.sh', 'text/x-sh'],
            ['test', 'text/x-sh'],
            ['test.csv', 'text/csv'],
            ['test.xlsx', 'spreadsheet/xlsx'],
            ['text.txt', 'text/plain'],
            [null, null]
        ];
    }

    /**
     * @dataProvider contentTypeDataProvider
     */
    public function testContentType(?string $filename, ?string $expected): void {
        $this->assertSame($expected, FileUtils::getContentType($filename));
    }

    public function testRecursiveChmod() {
        FileUtils::createDir($this->path);
        $path_perms = substr(sprintf('%o', fileperms($this->path)), -4);
        $file_1 = FileUtils::joinpaths($this->path, 'test.txt');
        file_put_contents($file_1, 'aaa');
        $dir_1 = FileUtils::joinPaths($this->path, 'dir1');
        FileUtils::createDir($dir_1);
        $file_2 = FileUtils::joinPaths($dir_1, 'test.txt');
        file_put_contents($file_2, 'bbb');
        chmod($dir_1, 0555);

        $this->assertTrue(FileUtils::recursiveChmod($this->path, 0777));
        $this->assertEquals($path_perms, substr(sprintf('%o', fileperms($this->path)), -4));
        $this->assertEquals("0777", substr(sprintf('%o', fileperms($dir_1)), -4));
        $this->assertEquals("0777", substr(sprintf('%o', fileperms($file_1)), -4));
        $this->assertEquals("0777", substr(sprintf('%o', fileperms($file_2)), -4));
    }

    /**
     * @runInSeparateProcess
     */
    public function testRecursiveChmodFail() {
        $this->getFunctionMock("app\\libraries", "chmod")
            ->expects($this->once())
            ->willReturn(false);
        FileUtils::createDir($this->path);
        file_put_contents(FileUtils::joinPaths($this->path, 'test.txt'), 'aaa');
        $this->assertFalse(FileUtils::recursiveChmod($this->path, 0777));
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
        $this->assertTrue(FileUtils::recursiveRmdir($base_dir));
    }

    private function buildFakeFile($filename, $part = 1, $err = 0, $size = 100) {
        $file_path = FileUtils::joinpaths($this->path, $filename);

        //zip files will already exist
        if (!file_exists($file_path)) {
            file_put_contents($file_path, str_repeat(' ', $size));
        }

        $_FILES["files{$part}"]['name'][] = $filename;
        $_FILES["files{$part}"]['type'][] = mime_content_type($file_path);
        $_FILES["files{$part}"]['size'][] = filesize($file_path);

        $tmpname = FileUtils::joinPaths($this->path, Utils::generateRandomString() . $filename);

        copy($file_path, $tmpname);

        $_FILES["files{$part}"]['tmp_name'][] = $tmpname;
        $_FILES["files{$part}"]['error'][] = $err;
    }

    public function testvalidateUploadedFilesGood() {
        FileUtils::createDir($this->path);
        $this->buildFakeFile('foo.txt');
        $this->buildFakeFile('foo2.txt');

        $stat = FileUtils::validateUploadedFiles($_FILES["files1"]);

        $this->assertCount(2, $stat);
        $this->assertEquals(
            $stat[0],
            ['name' => 'foo.txt',
             'type' => 'text/plain',
             'error' => 'No error.',
             'size' => 100,
             'is_zip' => false,
             'success' => true
            ]
        );

        $this->assertEquals(
            $stat[1],
            ['name' => 'foo2.txt',
             'type' => 'text/plain',
             'error' => 'No error.',
             'size' => 100,
             'is_zip' => false,
             'success' => true
            ]
        );
    }

    public function testvalidateUploadedFilesBad() {
        FileUtils::createDir($this->path);
        $this->buildFakeFile('bad.txt', 2, 3);
        $stat = FileUtils::validateUploadedFiles($_FILES["files2"]);

        $this->assertCount(1, $stat);
        $this->assertEquals(
            $stat[0],
            ['name' => 'bad.txt',
             'type' => 'text/plain',
             'error' => 'The file was only partially uploaded',
             'size' => 100,
             'is_zip' => false,
             'success' => false
             ]
        );

        $this->buildFakeFile('bad2.txt', 2, 4);
        $stat = FileUtils::validateUploadedFiles($_FILES["files2"]);

        $this->assertCount(2, $stat);
        $this->assertEquals(
            $stat[1],
            ['name' => 'bad2.txt',
             'type' => 'text/plain',
             'error' => 'No file was uploaded.',
             'size' => 100,
             'is_zip' => false,
             'success' => false
             ]
        );

        $this->buildFakeFile('bad3.txt', 2, 5);
        $this->buildFakeFile('bad3.txt', 2, 6);
        $this->buildFakeFile('bad3.txt', 2, 7);
        $this->buildFakeFile('bad3.txt', 2, 8);

        $stat = FileUtils::validateUploadedFiles($_FILES["files2"]);

        $this->assertCount(6, $stat);
        $this->assertEquals(
            $stat[1],
            ['name' => 'bad2.txt',
             'type' => 'text/plain',
             'error' => 'No file was uploaded.',
             'size' => 100,
             'is_zip' => false,
             'success' => false
             ]
        );

        $this->assertEquals(
            $stat[2],
            ['name' => 'bad3.txt',
             'type' => 'text/plain',
             'error' => 'Unknown error code.',
             'size' => 100,
             'is_zip' => false,
             'success' => false
             ]
        );

        $this->buildFakeFile('\?<>.txt', 2, 0);
        $stat = FileUtils::validateUploadedFiles($_FILES["files2"]);

        $this->assertCount(7, $stat);
        $this->assertEquals(
            $stat[6],
            ['name' => '\?<>.txt',
             'type' => 'text/plain',
             'error' => 'Invalid filename',
             'size' => 100,
             'is_zip' => false,
             'success' => false
             ]
        );
    }

    public function testvalidateUploadedFilesBig() {
        FileUtils::createDir($this->path);
        $this->buildFakeFile("big.txt", 3, 0, 100 + Utils::returnBytes(ini_get('upload_max_filesize')));
        $stat = FileUtils::validateUploadedFiles($_FILES["files3"]);

        $this->assertCount(1, $stat);
        $this->assertEquals(
            $stat[0],
            ['name' => 'big.txt',
             'type' => 'text/plain',
             'error' => 'File "big.txt" too large got (2.0000953674316MB)',
             'size' => 100 + Utils::returnBytes(ini_get('upload_max_filesize')),
             'is_zip' => false,
             'success' => false
             ]
        );

        $this->buildFakeFile("just_big_enough.txt", 3, 0, Utils::returnBytes(ini_get('upload_max_filesize')));
        $stat = FileUtils::validateUploadedFiles($_FILES["files3"]);

        $this->assertCount(2, $stat);
        $this->assertEquals(
            $stat[1],
            ['name' => 'just_big_enough.txt',
             'type' => 'text/plain',
             'error' => 'No error.',
             'size' =>  Utils::returnBytes(ini_get('upload_max_filesize')),
             'is_zip' => false,
             'success' => true
             ]
        );
    }

    public function testvalidateUploadedFilesFail() {
        $stat = FileUtils::validateUploadedFiles(null);
        $this->assertArrayHasKey("failed", $stat);
        $this->assertEquals($stat["failed"], "No files sent to validate");

        $stat = FileUtils::validateUploadedFiles([]);
        $this->assertArrayHasKey("failed", $stat);
        $this->assertEquals($stat["failed"], "No files sent to validate");
    }

    public function testvalidateUploadedFilesZipGood() {
        FileUtils::createDir($this->path);
        $zip = new \ZipArchive();
        $zip->open(FileUtils::joinPaths($this->path, 'test.zip'), \ZipArchive::CREATE);
        $zip->addFromString('testfile', "file test");
        $zip->close();

        $this->buildFakeFile('test.zip', 4);
        $stat = FileUtils::validateUploadedFiles($_FILES["files4"]);

        $this->assertCount(1, $stat);
        $this->assertEquals(
            $stat[0],
            ['name' => 'test.zip',
             'type' => 'application/zip',
             'error' => 'No error.',
             'size' => 9,
             'is_zip' => true,
             'success' => true
            ]
        );
    }

    public function testvalidateUploadedFilesZipBad() {
        FileUtils::createDir($this->path);
        $zip = new \ZipArchive();
        $zip->open(FileUtils::joinPaths($this->path, 'tes<>t.zip'), \ZipArchive::CREATE);
        $zip->addFromString('test222', "file test");
        $zip->addFromString('testttt>', "bad name");
        $zip->close();

        $this->buildFakeFile('tes<>t.zip', 5);
        $stat = FileUtils::validateUploadedFiles($_FILES["files5"]);

        $this->assertCount(1, $stat);
        $this->assertEquals(
            ['name' => 'tes<>t.zip',
             'type' => 'application/zip',
             'error' => 'Invalid filename',
             'size' => 17,
             'is_zip' => true,
             'success' => false
            ],
            $stat[0]
        );
    }

    private function getAllFilesSetup(): void {
        FileUtils::createDir($this->path);
        foreach (['a', 'b'] as $name) {
            file_put_contents(FileUtils::joinPaths($this->path, $name . '.txt'), $name);
        }
        foreach (['c', 'd'] as $name) {
            FileUtils::createDir(FileUtils::joinPaths($this->path, $name));
        }

        foreach (['.git', '.idea', '.svn', '__macosx'] as $dir) {
            FileUtils::createDir(FileUtils::joinPaths($this->path, $dir));
            FileUtils::createDir(FileUtils::joinPaths($this->path, 'c', $dir));
        }

        file_put_contents(FileUtils::joinPaths($this->path, '.DS_Store'), 'aa');
        file_put_contents(FileUtils::joinPaths($this->path, 'd', '.Ds_StOrE'), 'bb');
        FileUtils::createDir(FileUtils::joinPaths($this->path, 'c', 'e'));
        file_put_contents(FileUtils::joinPaths($this->path, 'c', 'f.py'), 'ff');
        file_put_contents(FileUtils::joinPaths($this->path, 'c', 'e', 'g.cpp'), 'gg');
        file_put_contents(FileUtils::joinPaths($this->path, 'd', 'h.h'), 'hh');
        file_put_contents(FileUtils::joinPaths($this->path, 'd', 'a.txt'), 'gg');
    }

    public function testGetAllFiles(): void {
        $this->getAllFilesSetup();
        $expected = [
            "a.txt" => [
              "name" => "a.txt",
              "path" => FileUtils::joinPaths($this->path, "a.txt"),
              "size" => 1,
              "relative_name" => "a.txt",
            ],
            "b.txt" => [
                "name" => "b.txt",
                "path" => FileUtils::joinPaths($this->path, "b.txt"),
                "size" => 1,
                "relative_name" => "b.txt"
            ],
            "c" => [
                "files" => [
                    "e" => [
                        "files" => [
                            "g.cpp" => [
                                "name" => "g.cpp",
                                "path" => FileUtils::joinPaths($this->path, "c", "e", "g.cpp"),
                                "size" => 2,
                                "relative_name" => "g.cpp",
                            ]
                        ],
                        "path" => FileUtils::joinPaths($this->path, "c/e")
                    ],
                    "f.py" => [
                        "name" => "f.py",
                        "path" => FileUtils::joinPaths($this->path, "c", "f.py"),
                        "size" => 2,
                        "relative_name" => "f.py"
                    ]
                ],
                "path" => FileUtils::joinPaths($this->path, "c")
            ],
            "d" => [
                "files" => [
                    "h.h" => [
                        "name" => "h.h",
                        "path" => FileUtils::joinPaths($this->path, "d", "h.h"),
                        "size" => 2,
                        "relative_name" => "h.h"
                    ],
                    "a.txt" => [
                        "name" => "a.txt",
                        "path" => FileUtils::joinPaths($this->path, 'd', 'a.txt'),
                        "size" => 2,
                        "relative_name" => "a.txt"
                    ]
                ],
                "path" => FileUtils::joinPaths($this->path, "d")
            ]
        ];
        $this->assertEquals($expected, FileUtils::getAllFiles($this->path));
    }

    public function testGetAllFilesFlatten() {
        $this->getAllFilesSetup();
        $expected = [
            "a.txt" => [
              "name" => "a.txt",
              "path" => FileUtils::joinPaths($this->path, "a.txt"),
              "size" => 1,
              "relative_name" => "a.txt",
            ],
            "b.txt" => [
                "name" => "b.txt",
                "path" => FileUtils::joinPaths($this->path, "b.txt"),
                "size" => 1,
                "relative_name" => "b.txt"
            ],
            "c/e/g.cpp" => [
                "name" => "g.cpp",
                "path" => FileUtils::joinPaths($this->path, "c", "e", "g.cpp"),
                "size" => 2,
                "relative_name" => "c/e/g.cpp",
            ],
            "c/f.py" => [
                "name" => "f.py",
                "path" => FileUtils::joinPaths($this->path, "c", "f.py"),
                "size" => 2,
                "relative_name" => "c/f.py"
            ],
            "d/h.h" => [
                "name" => "h.h",
                "path" => FileUtils::joinPaths($this->path, "d", "h.h"),
                "size" => 2,
                "relative_name" => "d/h.h"
            ],
            "d/a.txt" => [
                "name" => "a.txt",
                "path" => FileUtils::joinPaths($this->path, 'd', 'a.txt'),
                "size" => 2,
                "relative_name" => "d/a.txt"
            ]
        ];
        $this->assertEquals($expected, FileUtils::getAllFiles($this->path, [], true));
    }

    public function testGetAllFilesIngnoreFiles() {
        $this->getAllFilesSetup();
        $expected = [
            "b.txt" => [
                "name" => "b.txt",
                "path" => FileUtils::joinPaths($this->path, "b.txt"),
                "size" => 1,
                "relative_name" => "b.txt"
            ],
            "c" => [
                "files" => [
                    "e" => [
                        "files" => [],
                        "path" => FileUtils::joinPaths($this->path, "c/e")
                    ],
                    "f.py" => [
                        "name" => "f.py",
                        "path" => FileUtils::joinPaths($this->path, "c", "f.py"),
                        "size" => 2,
                        "relative_name" => "f.py"
                    ]
                ],
                "path" => FileUtils::joinPaths($this->path, "c")
            ],
            "d" => [
                "files" => [
                    "h.h" => [
                        "name" => "h.h",
                        "path" => FileUtils::joinPaths($this->path, "d", "h.h"),
                        "size" => 2,
                        "relative_name" => "h.h"
                    ]
                ],
                "path" => FileUtils::joinPaths($this->path, "d")
            ]
        ];
        $this->assertEquals($expected, FileUtils::getAllFiles($this->path, ['a.txt', 'g.cpp']));
    }

    public function testGetAllFilesIngnoreFilesFlatten() {
        $this->getAllFilesSetup();
        $expected = [
            "c/e/g.cpp" => [
                "name" => "g.cpp",
                "path" => FileUtils::joinPaths($this->path, "c", "e", "g.cpp"),
                "size" => 2,
                "relative_name" => "c/e/g.cpp",
            ],
            "c/f.py" => [
                "name" => "f.py",
                "path" => FileUtils::joinPaths($this->path, "c", "f.py"),
                "size" => 2,
                "relative_name" => "c/f.py"
            ],
            "d/h.h" => [
                "name" => "h.h",
                "path" => FileUtils::joinPaths($this->path, "d", "h.h"),
                "size" => 2,
                "relative_name" => "d/h.h"
            ]
        ];
        $this->assertEquals($expected, FileUtils::getAllFiles($this->path, ['a.txt', 'b.txt'], true));
    }

    public function testGetAllFilesTrimSearchPath() {
        $this->getAllFilesSetup();
        $expected = [
            0 => '/a.txt',
            1 => '/b.txt',
            2 => '/c/e/g.cpp',
            3 => '/c/f.py',
            4 => '/d/a.txt',
            5 => '/d/h.h',
        ];
        $this->assertEquals($expected, FileUtils::getAllFilesTrimSearchPath($this->path, strlen($this->path)));
    }

    public function areWordsInFileProvider() {
        return [
            ["this is a test\nfile that has some words in it", [], false],
            ["this is a test\nfile that has some words in it", ['test'], true],
            ["this is a test\nfile that has some words in it", ['foo'], false],
            ["this is a test\nfile that has some words in it", ['foo', 'words'], true],
            ["this is a test\nfile that has some words in it", ['foo', 'bar'], false],
        ];
    }

    /**
     * @dataProvider areWordsInFileProvider
     */
    public function testAreWordsInFile($contents, $words, $expected) {
        FileUtils::createDir($this->path);
        $test_file = FileUtils::joinPaths($this->path, 'test.txt');
        file_put_contents($test_file, $contents);
        $this->assertSame($expected, FileUtils::areWordsInFile($test_file, $words));
    }

    public function testAreWordsInFileCannotFindFile() {
        $this->expectException(FileReadException::class);
        $this->expectExceptionMessage('Unable to either locate or read the file contents');
        FileUtils::areWordsInFile(FileUtils::joinPaths($this->path, 'test.txt'), []);
    }

    public function testAreWordsInFileCannotReadFile() {
        FileUtils::createDir($this->path);
        $test_file = FileUtils::joinPaths($this->path, 'test.txt');
        file_put_contents($test_file, 'aaa');
        chmod($test_file, 0000);
        try {
            $this->expectException(FileReadException::class);
            $this->expectExceptionMessage('Unable to either locate or read the file contents');
            FileUtils::areWordsInFile($test_file, []);
        }
        finally {
            chmod($test_file, 0777);
        }
    }
}
