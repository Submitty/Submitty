<?php

namespace tests\unitTests\app\controllers\submission;

use app\controllers\student\SubmissionController;
use app\libraries\FileUtils;
use app\libraries\Utils;
use app\models\Gradeable;
use app\models\GradeableList;
use tests\unitTests\BaseUnitTest;

class SubmissionControllerTester extends BaseUnitTest {

    private $config = array();
    private $core;

    public function setUp() {
        $_REQUEST['action'] = 'upload';
        $_REQUEST['gradeable_id'] = 'test';
        $_POST['previous_files'] = "";
        $_POST['csrf_token'] = null;

        $config['tmp_path'] = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
        $config['semester'] = "test";
        $config['course'] = "test";
        $config['course_path'] = FileUtils::joinPaths($config['tmp_path'], "courses", $config['semester'],
            $config['course']);

        if (!FileUtils::createDir($config['course_path'], null, true)) {
            print("Could not create ".$config['course_path']);
        }

        if (!FileUtils::createDir(FileUtils::joinPaths($config['course_path'], "submissions"))) {
            print("Could not create ".FileUtils::joinPaths($config['course_path'], "submissions"));
        }

        if (!FileUtils::createDir(FileUtils::joinPaths($config['tmp_path'], "to_be_graded_interactive"))) {
            print("Could not create ".FileUtils::joinPaths($config['tmp_path'], "to_be_graded_interactive"));
        }

        $this->config = $config;

        $this->core = $this->mockCore($this->config);

        $gradeable = $this->createMock(Gradeable::class);
        $gradeable->method('getId')->willReturn("test");
        $gradeable->method('getName')->willReturn("Test Gradeable");
        $gradeable->method('getHighestVersion')->willReturn(0);

        $annotations = $this->getAnnotations();
        if (isset($annotations['method']['highestVersion'][0])) {
            $gradeable->method('getHighestVersion')->willReturn(intval($annotations['method']['highestVersion'][0]));
        }
        else {
            $gradeable->method('getHighestVersion')->willReturn(0);
        }

        if (isset($annotations['method']['numParts'][0])) {
            $gradeable->method('getNumParts')->willReturn(intval($annotations['method']['numParts'][0]));
        }
        else {
            $gradeable->method('getNumParts')->willReturn(1);
        }

        $gradeable->method('getMaxSize')->willReturn(1000000); // 1 MB

        $g_list = $this->createMock(GradeableList::class);
        $g_list->method('getSubmittableElectronicGradeables')->willReturn(array('test' => $gradeable));
        $this->core->method('loadModel')->willReturn($g_list);
    }

    /**
     * Cleanup routine for the tester. This deletes any folders/files we created in the tmp directory to hold our fake
     * uploaded files.
     */
    public function tearDown() {
        FileUtils::recursiveRmdir($this->config['tmp_path']);
    }

    public function setUploadFiles($name, $dir="", $part=1) {
        $src = FileUtils::joinPaths(__TEST_DATA__, "files", $dir, $name);
        $dst = FileUtils::joinPaths($this->config['tmp_path'], Utils::generateRandomString());
        copy($src, $dst);
        $_FILES["files{$part}"]['name'][] = $name;
        $_FILES["files{$part}"]['type'][] = FileUtils::getMimeType($src);
        $_FILES["files{$part}"]['size'][] = filesize($src);
        $_FILES["files{$part}"]['tmp_name'][] = $dst;
        $_FILES["files{$part}"]['error'][] = null;
    }

    public function runController() {
        $controller = new SubmissionController($this->core);
        return $controller->run();
    }

    /**
     * Basic upload, only one part and one file, simple sanity check.
     */
    public function testUploadOneBucket() {
        $this->setUploadFiles('test1.txt');
        $return = $this->runController();
        $this->assertFalse($return['error'], "Error thrown: {$return['message']}");
        $this->assertTrue($return['success']);

        $tmp = FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser", "1");
        $this->assertStringEqualsFile(FileUtils::joinPaths($tmp, 'test1.txt'), "a");
        $pattern = '/[0-9]{4}\-[0-1][0-9]\-[0-3][0-9] [0-2][0-9]:[0-5][0-9]:[0-5][0-9]/';
        $this->assertRegExp($pattern, file_get_contents(FileUtils::joinPaths($tmp, ".submit.timestamp")));
        $iter = new \FilesystemIterator($tmp);
        foreach ($iter as $entry) {
            $this->assertFalse($entry->isDir());
            $this->assertFalse($entry->isLink());
            $this->assertTrue($entry->isFile());
            $this->assertTrue(in_array($entry->getFilename(), array('test1.txt', '.submit.timestamp')));
        }
    }

    /**
     * Test what happens if we have two parts
     *
     * @numParts 2
     */
    public function testUploadTwoBuckets() {
        $this->setUploadFiles('test1.txt');
        $this->setUploadFiles('test2.txt', '', 2);
        $return = $this->runController();
        $this->assertFalse($return['error'], "Error thrown: {$return['message']}");
        $this->assertTrue($return['success']);

        $tmp = FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser", "1");
        $iter = new \RecursiveDirectoryIterator($tmp);
        while ($iter->getPathname() !== "" && $iter->getFilename() !== "") {
            if ($iter->isDot()) {
                $iter->next();
                continue;
            }
            else if ($iter->isFile()) {
                $this->assertEquals(".submit.timestamp", $iter->getFilename());
            }
            else if ($iter->isDir()) {
                $this->assertTrue(in_array($iter->getFilename(), array('part1', 'part2')));
                $iter2 = $iter->getChildren();
                while ($iter2 !== "" && $iter2->getFilename() !== "") {
                    if ($iter2->isDot()) {
                        $iter2->next();
                        continue;
                    }
                    else if ($iter2->isFile()) {
                        if ($iter->getFilename() === "part1") {
                            $this->assertEquals($iter2->getFilename(), "test1.txt");
                            $this->assertStringEqualsFile($iter2->getPathname(), "a");
                        }
                        else if ($iter->getFilename() === "part2") {
                            $this->assertEquals($iter2->getFilename(), "test2.txt");
                            $this->assertStringEqualsFile($iter2->getPathname(), "b");
                        }
                    }
                    else {
                        $this->fail("Part directory should not contain a directory itself.");
                    }

                    $iter2->next();
                }
            }
            else {
                $this->fail("Unknown type found in test directory.");
            }
            $iter->next();
        }
    }

    /**
     * Test what happens if we're uploading a zip that contains a directory.
     */
    public function testZipWithDirectory() {
        $this->setUploadFiles('directory_inside.zip');
        $return = $this->runController();

        $this->assertFalse($return['error'], "Error: {$return['message']}");
        $this->assertTrue($return['success']);

        $tmp = FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser", "1");
        $iter = new \RecursiveDirectoryIterator($tmp);
        while ($iter->getPathname() !== "" && $iter->getFilename() !== "") {
            if ($iter->isDot()) {
                $iter->next();
                continue;
            }
            else if ($iter->isFile()) {
                $this->assertTrue(in_array($iter->getFilename(), array(".submit.timestamp", "test2.txt")));
            }
            else if ($iter->isDir()) {
                $this->assertEquals("testDir", $iter->getFilename());
                $iter2 = $iter->getChildren();
                while ($iter2 !== "" && $iter2->getFilename() !== "") {
                    if ($iter2->isDot()) {
                        $iter2->next();
                        continue;
                    }
                    else if ($iter2->isFile()) {
                        $this->assertEquals("test1.txt", $iter2->getFilename());
                    }
                    else {
                        $this->fail("Part directory should not contain a directory itself.");
                    }

                    $iter2->next();
                }
            }
            else {
                $this->fail("Unknown type found in test directory.");
            }
            $iter->next();
        }
    }

    /**
     * Test uploading a zip that contains a file and a zip. We only unzip one level so we the inner zip should
     * be left alone.
     */
    public function testZipInsideZip() {
        $this->setUploadFiles('zip_inside.zip');
        $return = $this->runController();

        $this->assertFalse($return['error'], "Error: {$return['message']}");
        $this->assertTrue($return['success']);

        $tmp = FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser", "1");
        $iter = new \RecursiveDirectoryIterator($tmp);
        while ($iter->getPathname() !== "" && $iter->getFilename() !== "") {
            if ($iter->isDot()) {
                $iter->next();
                continue;
            }
            else if ($iter->isFile()) {
                $this->assertTrue(in_array($iter->getFilename(),
                    array(".submit.timestamp", "test1.txt", "basic_zip.zip")));
            }
            else {
                $this->fail("Unknown type found in test directory.");
            }
            $iter->next();
        }
    }

    /**
     * This tests what happens if we upload a zip that contains "test.txt" and "test2.txt" and try
     * uploading "test.txt" to the server, in that order. The free "test.txt" file should overwrite the one
     * in the zip (the one not in a zip contains a single 'a' while the two files in the zip are blank).
     */
    public function testSameFilenameInZip() {
        $this->setUploadFiles('zippedfiles.zip', 'same_filenames_in_zip');
        $this->setUploadFiles('test.txt', 'same_filenames_in_zip');

        $return = $this->runController();

        $this->assertFalse($return['error'], "Error: {$return['message']}");
        $this->assertTrue($return['success']);

        $tmp = FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser", "1");
        $this->assertStringEqualsFile(FileUtils::joinPaths($tmp, "test.txt"), "a");
        $this->assertStringEqualsFile(FileUtils::joinPaths($tmp, "test2.txt"), "");
    }

    /**
     * This tests the same thing as  testSameFilenameInZip(), however we submit "test.txt" before "zippedfiles.zip"
     */
    public function testSameFilenameInZipReversed() {
        $this->setUploadFiles('test.txt', 'same_filenames_in_zip');
        $this->setUploadFiles('zippedfiles.zip', 'same_filenames_in_zip');

        $return = $this->runController();

        $this->assertFalse($return['error'], "Error: {$return['message']}");
        $this->assertTrue($return['success']);

        $tmp = FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser", "1");
        $this->assertStringEqualsFile(FileUtils::joinPaths($tmp, "test.txt"), "");
        $this->assertStringEqualsFile(FileUtils::joinPaths($tmp, "test2.txt"), "");
    }

    /**
     * Because our system does not recursively expand zip files with reckless disregard, we only need to worry
     * about someone hiding a weird size in the root of the zip.
     */
    public function testUploadZipBomb() {
        $this->setUploadFiles('zip_bomb.zip', 'zip_bomb');
        $return = $this->runController();

        $this->assertTrue($return['error'], "An error should have happened");
        $this->assertEquals("File(s) uploaded too large.  Maximum size is 1000 kb. Uploaded file(s) was 10240 kb.",
            $return['message']);
        $this->assertFalse($return['success']);
        $tmp = FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser", "1");
        $this->assertFalse(is_dir($tmp));
    }

    /**
     * Upload test cases:
     * 1) normal upload one bucket (no zips)
     * 2) normal upload two buckets (no zips)
     * 3) zip upload one bucket (no folders)
     * 4) zip upload two buckets (no folders)
     * 5) zip upload one bucket (folders)
     * 6) zip upload two buckets (folders)
     * 7) zip bomb
     */
}