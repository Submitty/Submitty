<?php

namespace tests\unitTests\app\controllers\submission;

use app\controllers\student\SubmissionController;
use app\libraries\FileUtils;
use app\libraries\Utils;
use app\models\Gradeable;
use app\models\GradeableList;
use tests\unitTests\BaseUnitTest;

class SubmissionControllerTester extends BaseUnitTest {

    /**
     * @var array
     */
    private $config = array();
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $core;

    public function setUp() {
        $_REQUEST['action'] = 'upload';
        $_REQUEST['gradeable_id'] = 'test';
        $_REQUEST['svn_checkout'] = false;
        $_POST['previous_files'] = "";
        $_POST['csrf_token'] = "";

        $config['tmp_path'] = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
        $config['semester'] = "test";
        $config['course'] = "test";
        $config['course_path'] = FileUtils::joinPaths($config['tmp_path'], "courses", $config['semester'],
            $config['course']);

        $this->assertTrue(FileUtils::createDir($config['course_path'], null, true));
        $this->assertTrue(FileUtils::createDir(FileUtils::joinPaths($config['course_path'], "submissions")));
        $this->assertTrue(FileUtils::createDir(FileUtils::joinPaths($config['tmp_path'], "to_be_graded_interactive")));

        $this->config = $config;

        $this->core = $this->createMockCore($this->config);

        $highest_version = 0;
        $num_parts = 1;
        $max_size = 1000000; // 1 MB

        $annotations = $this->getAnnotations();
        if (isset($annotations['method']['highestVersion'][0])) {
            $highest_version = intval($annotations['method']['highestVersion'][0]);
        }

        if (isset($annotations['method']['numParts'][0])) {
            $num_parts = intval($annotations['method']['numParts'][0]);
        }

        if (isset($annotations['method']['maxSize'][0])) {
            $max_size = intval($annotations['method']['maxSize'][0]);
        }

        $this->core->method('loadModel')->willReturn($this->createMockGradeableList($highest_version, $num_parts, $max_size));
    }

    /**
     * Helper method to generate a mocked gradeable list with one gradeable. We can use annotations in our testcases
     * to set various aspects of the gradeable, namely @highestVersion, @numParts, and @maxSize for
     * highest version of submission, number of parts, and filesize respectively.
     *
     * @param int    $highest_version
     * @param int    $num_parts
     * @param double $max_size
     *
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function createMockGradeableList($highest_version = 0, $num_parts = 1, $max_size = 1000000.) {
        $gradeable = $this->createMock(Gradeable::class);
        $gradeable->method('getId')->willReturn("test");
        $gradeable->method('getName')->willReturn("Test Gradeable");

        $gradeable->method('getHighestVersion')->willReturn(intval($highest_version));
        $gradeable->method('getNumParts')->willReturn(intval($num_parts));
        $gradeable->method('getMaxSize')->willReturn($max_size);

        $g_list = $this->createMock(GradeableList::class);
        $g_list->method('getSubmittableElectronicGradeables')->willReturn(array('test' => $gradeable));
        return $g_list;
    }

    /**
     * Cleanup routine for the tester. This deletes any folders/files we created in the tmp directory to hold our fake
     * uploaded files.
     */
    public function tearDown() {
        $this->assertTrue(FileUtils::recursiveRmdir($this->config['tmp_path']));
    }

    /**
     * This adds a new entry to $_FILES, moving the file to the directory we've created for the tests.
     *
     * @param $name
     * @param string $dir
     * @param int $part
     */
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

    /**
     * Runs the upload function in the controller using our mocked Core object, and then clearing out the $_FILES
     * array.
     *
     * @param $core
     * @return mixed
     */
    public function runController($core=null) {
        if ($core === null) {
            $core = $this->core;
        }
        /** @noinspection PhpParamsInspection */
        $controller = new SubmissionController($core);
        $return = $controller->run();
        $_FILES = array();
        return $return;
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
        $files = array();
        foreach ($iter as $entry) {
            $this->assertFalse($entry->isDir());
            $this->assertFalse($entry->isLink());
            $this->assertTrue($entry->isFile());
            $files[] = $entry->getFilename();
        }
        sort($files);
        $this->assertEquals(array('.submit.timestamp', 'test1.txt'), $files);
        $touch_file = implode("__", array($this->config['semester'], $this->config['course'], "test", "testUser", "1"));
        $this->assertFileExists(FileUtils::joinPaths($this->config['tmp_path'], "to_be_graded_interactive", $touch_file));
        $tmp = FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser");
        foreach (new \FilesystemIterator($tmp) as $iter) {
            if ($iter->isDir()) {
                $this->assertEquals("1", $iter->getFilename());
            }
            else {
                $this->assertTrue($iter->isFile());
                $this->assertEquals("user_assignment_settings.json", $iter->getFilename());
                $json = FileUtils::readJsonFile($iter->getPathname());
                $this->assertEquals(1, $json['active_version']);
                $this->assertTrue(isset($json['history']));
                $this->assertEquals(1, count($json['history']));
                $this->assertEquals(1, $json['history'][0]['version']);
                $this->assertRegExp('/[0-9]{4}\-[0-1][0-9]\-[0-3][0-9] [0-2][0-9]:[0-5][0-9]:[0-5][0-9]/', $json['history'][0]['time']);
            }
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
        $parts = array();
        $files = array();
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
                $parts[] = $iter->getFilename();
                $iter2 = $iter->getChildren();
                while ($iter2 !== "" && $iter2->getFilename() !== "") {
                    if ($iter2->isDot()) {
                        $iter2->next();
                        continue;
                    }
                    else if ($iter2->isFile()) {
                        $files[] = $iter2->getFilename();
                        if ($iter->getFilename() === "part1") {
                            $this->assertEquals($iter2->getFilename(), "test1.txt");
                            $this->assertStringEqualsFile($iter2->getPathname(), "a");
                        }
                        else if ($iter->getFilename() === "part2") {
                            $this->assertEquals($iter2->getFilename(), "test2.txt");
                            $this->assertStringEqualsFile($iter2->getPathname(), "b");
                        }
                        else {
                            $this->fail("There should only be test1.txt or test2.txt in these directories");
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
        sort($parts);
        $this->assertEquals(array('part1', 'part2'), $parts);
        sort($files);
        $this->assertEquals(array('test1.txt', 'test2.txt'), $files);
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
        $filenames = array();
        while ($iter->getPathname() !== "" && $iter->getFilename() !== "") {
            if ($iter->isDot()) {
                $iter->next();
                continue;
            }
            else if ($iter->isFile()) {
                $filenames[] = $iter->getFilename();
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
        sort($filenames);
        $this->assertEquals(array(".submit.timestamp", "test2.txt"), $filenames);
    }

    /**
     * Upload a second version of a gradeable with no previous files and different files per upload. Test
     * that both versions exist and neither bled over to the other.
     */
    public function testSecondVersionNoPrevious() {
        $this->setUploadFiles('test1.txt');
        $return = $this->runController();
        $this->assertFalse($return['error'], "Error: {$return['message']}");
        $this->assertTrue($return['success']);
        $tmp = FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser", "1");
        $files = array();
        foreach (new \FilesystemIterator($tmp) as $file) {
            $this->assertFalse($file->isDir());
            $files[] = $file->getFilename();
        }
        sort($files);
        $this->assertEquals(array('.submit.timestamp', 'test1.txt'), $files);

        $this->setUploadFiles('test2.txt');
        $core = $this->createMockCore($this->config);
        $core->method('loadModel')->willReturn($this->createMockGradeableList(1));
        $return = $this->runController($core);
        $this->assertFalse($return['error'], "Error: {$return['message']}");
        $this->assertTrue($return['success']);
        $tmp = FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser", "2");
        $files = array();
        foreach (new \FilesystemIterator($tmp) as $file) {
            $this->assertFalse($file->isDir());
            $files[] = $file->getFilename();
        }
        sort($files);
        $this->assertEquals(array('.submit.timestamp', 'test2.txt'), $files);

        $tmp = FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser");

        $dirs = array();
        foreach (new \FilesystemIterator($tmp) as $iter) {
            if ($iter->isDir()) {
                $dirs[] = $iter->getFilename();
            }
            else {
                $this->assertTrue($iter->isFile());
                $this->assertEquals("user_assignment_settings.json", $iter->getFilename());
                $json = FileUtils::readJsonFile($iter->getPathname());
                $this->assertEquals(2, $json['active_version']);
                $this->assertTrue(isset($json['history']));
                $this->assertEquals(2, count($json['history']));
                $this->assertEquals(1, $json['history'][0]['version']);
                $this->assertRegExp('/[0-9]{4}\-[0-1][0-9]\-[0-3][0-9] [0-2][0-9]:[0-5][0-9]:[0-5][0-9]/', $json['history'][0]['time']);
                $this->assertEquals(2, $json['history'][1]['version']);
                $this->assertRegExp('/[0-9]{4}\-[0-1][0-9]\-[0-3][0-9] [0-2][0-9]:[0-5][0-9]:[0-5][0-9]/', $json['history'][1]['time']);
            }
        }
        sort($dirs);
        $this->assertEquals(array('1', '2'), $dirs);
    }

    /**
     * @numParts 2
     */
    public function testSecondVersionPreviousTwoParts() {
        $this->setUploadFiles("test1.txt", "", 1);
        $this->setUploadFiles("test1.txt", "", 2);
        $return = $this->runController();
        $this->assertFalse($return['error'], "Error: {$return['message']}");
        $this->assertTrue($return['success']);

        $_POST['previous_files'] = json_encode(array(0 => array('test1.txt'), 1 => array('test1.txt')));
        $core = $this->createMockCore($this->config);
        $core->method('loadModel')->willReturn($this->createMockGradeableList(1, 2));
        $this->setUploadFiles("test2.txt", "", 1);
        $this->setUploadFiles("test2.txt", "", 2);
        $return = $this->runController($core);
        $this->assertFalse($return['error'], "Error: {$return['message']}");
        $this->assertTrue($return['success']);
    }

    /**
     * Upload a second version of a gradeable that includes previous files, but there's no overlap in file names
     * so we should have one file in version 1 and two files in version 2
     */
    public function testSecondVersionPreviousNoOverlap() {
        $this->setUploadFiles('test1.txt');
        $return = $this->runController();
        $this->assertFalse($return['error'], "Error: {$return['message']}");
        $this->assertTrue($return['success']);
        $tmp = FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser", "1");
        $files = array();
        foreach (new \FilesystemIterator($tmp) as $file) {
            $this->assertFalse($file->isDir());
            $files[] = $file->getFilename();
        }
        sort($files);
        $this->assertEquals(array('.submit.timestamp', 'test1.txt'), $files);

        $this->setUploadFiles('test2.txt');
        $_POST['previous_files'] = json_encode(array(array('test1.txt')));
        $core = $this->createMockCore($this->config);
        $core->method('loadModel')->willReturn($this->createMockGradeableList(1));
        $return = $this->runController($core);
        $this->assertFalse($return['error'], "Error: {$return['message']}");
        $this->assertTrue($return['success']);
        $tmp = FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser", "2");
        $files = array();
        foreach (new \FilesystemIterator($tmp) as $file) {
            $this->assertFalse($file->isDir());
            $files[] = $file->getFilename();
        }
        sort($files);
        $this->assertEquals(array('.submit.timestamp', 'test1.txt', 'test2.txt'), $files);
    }

    /**
     * Upload a second version that has previous files that has the same filename as the file that's being uploaded.
     * This should only include the version that was uploaded (and not use the previous).
     */
    public function testSecondVersionPreviousOverlap() {
        $this->setUploadFiles('test1.txt');
        $return = $this->runController();
        $this->assertFalse($return['error'], "Error: {$return['message']}");
        $this->assertTrue($return['success']);
        $tmp = FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser", "1");
        $files = array();
        foreach (new \FilesystemIterator($tmp) as $file) {
            $this->assertFalse($file->isDir());
            $files[] = $file->getFilename();
        }
        sort($files);
        $this->assertEquals(array('.submit.timestamp', 'test1.txt'), $files);

        $this->setUploadFiles('test1.txt', 'overlap');
        $_POST['previous_files'] = json_encode(array(array('test1.txt')));
        $core = $this->createMockCore($this->config);
        $core->method('loadModel')->willReturn($this->createMockGradeableList(1));
        $return = $this->runController($core);
        $this->assertFalse($return['error'], "Error: {$return['message']}");
        $this->assertTrue($return['success']);
        $tmp = FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser", "2");
        $files = array();
        foreach (new \FilesystemIterator($tmp) as $file) {
            $this->assertFalse($file->isDir());
            $files[] = $file->getFilename();
            if ($file->getFilename() === "test1.txt") {
                $this->assertStringEqualsFile($file->getPathname(), "new_file");
            }
        }
        sort($files);
        $this->assertEquals(array('.submit.timestamp', 'test1.txt'), $files);
    }

    /**
     * This tests what happens when we upload a second version of the gradeable that is a zip that contains a file
     * that overlaps the file from the first version.
     */
    public function testSecondVersionPreviousOverlapZip() {
        $this->setUploadFiles('test1.txt');
        $return = $this->runController();
        $this->assertFalse($return['error'], "Error: {$return['message']}");
        $this->assertTrue($return['success']);
        $tmp = FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser", "1");
        $files = array();
        foreach (new \FilesystemIterator($tmp) as $file) {
            $this->assertFalse($file->isDir());
            $files[] = $file->getFilename();
        }
        sort($files);
        $this->assertEquals(array('.submit.timestamp', 'test1.txt'), $files);

        $this->setUploadFiles('overlap.zip', 'overlap');
        $_POST['previous_files'] = json_encode(array(array('test1.txt')));
        $core = $this->createMockCore($this->config);
        $core->method('loadModel')->willReturn($this->createMockGradeableList(1));
        $return = $this->runController($core);
        $this->assertFalse($return['error'], "Error: {$return['message']}");
        $this->assertTrue($return['success']);
        $tmp = FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser", "2");
        $files = array();
        foreach (new \FilesystemIterator($tmp) as $file) {
            $this->assertFalse($file->isDir());
            $files[] = $file->getFilename();
            if ($file->getFilename() === "test1.txt") {
                $this->assertStringEqualsFile($file->getPathname(), "new_file");
            }
        }
        sort($files);
        $this->assertEquals(array('.submit.timestamp', 'test1.txt'), $files);
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
        $files = array();
        while ($iter->getPathname() !== "" && $iter->getFilename() !== "") {
            if ($iter->isDot()) {
                $iter->next();
                continue;
            }
            else if ($iter->isFile()) {
                $files[] = $iter->getFilename();
            }
            else {
                $this->fail("Unknown type found in test directory.");
            }
            $iter->next();
        }
        sort($files);
        $this->assertEquals(array(".submit.timestamp", "basic_zip.zip", "test1.txt"), $files);
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
     * This tests the same thing as testSameFilenameInZip(), however we submit "test.txt" before "zippedfiles.zip"
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
        $files = array();
        foreach (new \FilesystemIterator($tmp) as $iter) {
            $this->assertTrue($iter->isFile());
            $files[] = $iter->getFilename();
        }
        sort($files);
        $this->assertEquals(array('.submit.timestamp', 'test.txt', 'test2.txt'), $files);

    }

    public function testFilenameWithSpaces() {
        $this->setUploadFiles("filename with spaces.txt");
        $return = $this->runController();
        $this->assertFalse($return['error'], "Error: {$return['message']}");
        $this->assertTrue($return['success']);

        $tmp = FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser", "1");
        $files = array();
        foreach (new \FilesystemIterator($tmp) as $iter) {
            $this->assertTrue($iter->isFile());
            $files[] = $iter->getFilename();
        }
        sort($files);
        $this->assertEquals(array('.submit.timestamp', 'filename with spaces.txt'), $files);
    }

    public function testZipContaingFilesWithSpaces() {
        $this->setUploadFiles('contains_spaces.zip');
        $return = $this->runController();
        $this->assertFalse($return['error'], "Error: {$return['message']}");
        $this->assertTrue($return['success']);
        $tmp = FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser", "1");
        $files = array();
        $iter = new \RecursiveDirectoryIterator($tmp);
        while ($iter->getPathname() !== "" && $iter->getFilename() !== "") {
            if ($iter->isDot()) {
                $iter->next();
                continue;
            }
            else if ($iter->isDir()) {
                $this->assertEquals("folder with spaces", $iter->getFilename());
                foreach (new \FilesystemIterator($iter->getPathname()) as $iter2) {
                    $this->assertTrue($iter2->isFile());
                    $this->assertEquals("filename with spaces2.txt", $iter->getFilename());
                }
            }
            else if ($iter->isFile()) {
                $files[] = $iter->getFilename();
            }
            else {
                $this->fail("Invalid type found in upload");
            }
            $iter->next();
        }

        sort($files);
        $this->assertEquals(array('.submit.timestamp', 'filename with spaces.txt'), $files);
    }

    public function testSvnUpload() {
        $_REQUEST['svn_checkout'] = "true";
        $return = $this->runController();
        $this->assertFalse($return['error'], "Error: {$return['message']}");
        $this->assertTrue($return['success']);

        $tmp = FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser", "1");
        $files = array();
        foreach (new \FilesystemIterator($tmp) as $iter) {
            $this->assertTrue($iter->isFile());
            $files[] = $iter->getFilename();
        }
        sort($files);
        $this->assertEquals(array('.submit.SVN_CHECKOUT', '.submit.timestamp'), $files);
        $touch_file = implode("__", array($this->config['semester'], $this->config['course'], "test", "testUser", "1"));
        $this->assertFileExists(FileUtils::joinPaths($this->config['tmp_path'], "to_be_graded_interactive", $touch_file));
    }

    public function testErrorNotSetCsrfToken() {
        $_POST['csrf_token'] = null;
        $return = $this->runController();
        $this->assertTrue($return['error']);
        $this->assertEquals("Invalid CSRF token.", $return['message']);
        $this->assertFalse($return['success']);
    }

    public function testErrorInvalidCsrfToken() {
        $config = $this->config;
        $config['csrf_token'] = false;
        $core = $this->createMockCore($config);
        $return = $this->runController($core);
        $this->assertTrue($return['error']);
        $this->assertEquals("Invalid CSRF token.", $return['message']);
        $this->assertFalse($return['success']);
    }

    /**
     * Test that error is thrown when trying to upload to a gradeable id that does not exist in
     * our gradeable list
     */
    public function testErrorInvalidGradeableId() {
        $_REQUEST['gradeable_id'] = "fake";
        $return = $this->runController();
        $this->assertTrue($return['error']);
        $this->assertEquals("Invalid gradeable id 'fake'", $return['message']);
        $this->assertFalse($return['success']);
    }

    /**
     * Test that error is thrown when system is trying to make a folder for the assignment (in the submissions
     * folder) and it cannot.
     */
    public function testFailureToCreateGradeableFolder() {
        $config = $this->config;
        $config['tmp_path'] = "invalid_folder_that_does_not_exist";
        $config['course_path'] = "invalid_folder_that_does_not_exist";
        $core = $this->createMockCore($config);
        $core->method('loadModel')->willReturn($this->createMockGradeableList());
        $return = $this->runController($core);
        $this->assertTrue($return['error']);
        $this->assertEquals("Failed to make folder for this assignment.", $return['message']);
        $this->assertFalse($return['success']);
    }

    public function testFailureToCreateStudentFolder() {
        FileUtils::createDir(FileUtils::joinPaths($this->config['course_path'], "submissions", "test"), 0444);
        $return = $this->runController();
        $this->assertTrue($return['error']);
        $this->assertEquals("Failed to make folder for this assignment for the user.", $return['message']);
        $this->assertFalse($return['success']);
        FileUtils::recursiveChmod($this->config['course_path'], 0777);
    }

    public function testFailureToCreateVersionFolder() {
        FileUtils::createDir(FileUtils::joinPaths($this->config['course_path'], "submissions", "test"));
        FileUtils::createDir(FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser"), 0444);
        $return = $this->runController();
        $this->assertTrue($return['error']);
        $this->assertEquals("Failed to make folder for the current version.", $return['message']);
        $this->assertFalse($return['success']);
        FileUtils::recursiveChmod($this->config['course_path'], 0777);
    }

    /**
     * @numParts 2
     */
    public function testFailureToCreatePartFolder() {
        FileUtils::createDir(FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser"), null, true);
        FileUtils::createDir(FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser", "1"), 0444);
        $return = $this->runController();
        $this->assertTrue($return['error']);
        $this->assertEquals("Failed to make the folder for part 1.", $return['message']);
        $this->assertFalse($return['success']);
        FileUtils::recursiveChmod($this->config['course_path'], 0777);
    }

    public function testFileUploadError() {
        $_FILES["files1"]['name'][] = "test.txt";
        $_FILES["files1"]['type'][] = "";
        $_FILES["files1"]['size'][] = 0;
        $_FILES["files1"]['tmp_name'][] = "";
        $_FILES["files1"]['error'][] = UPLOAD_ERR_PARTIAL;
        $return = $this->runController();
        $this->assertTrue($return['error']);
        $this->assertEquals("Upload Failed: test.txt failed to upload. Error message: The file was only partially uploaded.", $return['message']);
        $this->assertFalse($return['success']);
    }

    public function testNoFilesToSubmit() {
        $return = $this->runController();
        $this->assertTrue($return['error']);
        $this->assertEquals("No files to be submitted.", $return['message']);
        $this->assertFalse($return['success']);
    }

    public function testErrorPreviousFilesFirstVersion() {
        $_POST['previous_files'] = json_encode(array(0=>array('test.txt')));
        $return = $this->runController();
        $this->assertTrue($return['error']);
        $this->assertEquals("No submission found. There should not be any files from a previous submission.", $return['message']);
        $this->assertFalse($return['success']);
    }

    /**
     * @highestVersion 2
     */
    public function testErrorMissingPreviousFolder() {
        $_POST['previous_files'] = json_encode(array(0 => array('test.txt')));
        $return = $this->runController();
        $this->assertTrue($return['error']);
        $this->assertEquals("Files from previous submission not found. Folder for previous submission does not exist.", $return['message']);
        $this->assertFalse($return['success']);
    }

    public function testErrorMissingPreviousFile() {
        $this->setUploadFiles('test1.txt');
        $return = $this->runController();
        $this->assertTrue($return['success']);

        $_POST['previous_files'] = json_encode(array(0 => array('missing.txt')));
        $this->setUploadFiles('test1.txt');
        $core = $this->createMockCore($this->config);
        $core->method('loadModel')->willReturn($this->createMockGradeableList(1));
        $return = $this->runController($core);
        $this->assertTrue($return['error']);
        $this->assertEquals("File 'missing.txt' does not exist in previous submission.", $return['message']);
        $this->assertFalse($return['success']);
    }

    /**
     * We are not running through all possible invalid filenames (as the list might grow) as that is tested elsewhere,
     * just that we're using that function at all really.
     */
    public function testInvalidFilename() {
        $this->setUploadFiles('in"valid.txt', 'invalid_files');
        $return = $this->runController();
        $this->assertTrue($return['error']);
        $this->assertEquals("Error: You may not use quotes, backslashes or angle brackets in your file name in\"valid.txt.", $return['message']);
        $this->assertFalse($return['success']);
    }

    public function testInvalidFilenameInZip() {
        $this->setUploadFiles("invalid.zip", "invalid_files");
        $return = $this->runController();
        $this->assertTrue($return['error']);
        $this->assertEquals("Error: You may not use quotes, backslashes or angle brackets in your filename for files inside invalid.zip.", $return['message']);
        $this->assertFalse($return['success']);
    }

    /**
     * @maxSize 0
     */
    public function testErrorFileTooBig() {
        $this->setUploadFiles('test1.txt');
        $return = $this->runController();
        $this->assertTrue($return['error']);
        $this->assertEquals("File(s) uploaded too large.  Maximum size is 0 kb. Uploaded file(s) was 0.001 kb.", $return['message']);
        $this->assertFalse($return['success']);
    }

    /**
     * Because our system does not recursively expand zip files with reckless disregard, we only need to worry
     * about someone hiding a big sized file in the outermost zip.
     */
    public function testErrorFilesInZipTooBig() {
        $this->setUploadFiles('zip_bomb.zip', 'zip_bomb');
        $return = $this->runController();

        $this->assertTrue($return['error'], "An error should have happened");
        $this->assertEquals("File(s) uploaded too large.  Maximum size is 1000 kb. Uploaded file(s) was 10240 kb.",
            $return['message']);
        $this->assertFalse($return['success']);
        $tmp = FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser", "1");
        $this->assertFalse(is_dir($tmp));
    }

    public function testErrorOnBrokenZip() {
        $this->setUploadFiles('broken.zip');
        $return = $this->runController();
        $this->assertTrue($return['error']);
        $this->assertEquals("Could not properly unpack zip file. Error message: Zip archive is inconsistent.", $return['message']);
        $this->assertFalse($return['success']);
    }

    public function testErrorOnCopyingPrevious() {
        $this->setUploadFiles('test1.txt');
        $return = $this->runController();
        $this->assertFalse($return['error'], "Error: {$return['message']}");
        $this->assertTrue($return['success']);
        $prev = FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser", "1", "test1.txt");

        $_POST['previous_files'] = json_encode(array(0 => array('test1.txt')));
        chmod($prev, 0000);
        $core = $this->createMockCore($this->config);
        $core->method('loadModel')->willReturn($this->createMockGradeableList(1));
        $return = $this->runController($core);
        $this->assertTrue($return['error']);
        $this->assertEquals("Failed to copy previously submitted file test1.txt to current submission.", $return['message']);
        $this->assertFalse($return['success']);
        chmod($prev, 0777);
    }

    public function testErrorOnCopyingFile() {
        $this->setUploadFiles('test1.txt');
        FileUtils::createDir(FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser"), null, true);
        FileUtils::createDir(FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser", "1"), 0444);
        $return = $this->runController();
        $this->assertTrue($return['error']);
        $this->assertEquals("Failed to copy uploaded file test1.txt to current submission.", $return['message']);
        $this->assertFalse($return['success']);
    }

    public function testErrorCleanupTempFiles() {
        $src = FileUtils::joinPaths(__TEST_DATA__, "files", "test1.txt");
        $dst_dir = FileUtils::joinPaths($this->config['tmp_path'], "test_files");
        $dst = FileUtils::joinPaths($dst_dir, Utils::generateRandomString());
        FileUtils::createDir($dst_dir);
        copy($src, $dst);
        $_FILES["files1"]['name'][] = "test1.txt";
        $_FILES["files1"]['type'][] = FileUtils::getMimeType($src);
        $_FILES["files1"]['size'][] = filesize($src);
        $_FILES["files1"]['tmp_name'][] = $dst;
        $_FILES["files1"]['error'][] = null;
        chmod($dst_dir, 0550);
        $return = $this->runController();
        $this->assertTrue($return['error']);
        $this->assertEquals("Failed to delete the uploaded file test1.txt from temporary storage.", $return['message']);
        $this->assertFalse($return['success']);
        chmod($dst_dir, 0777);
    }

    /**
     * Test that we cannot just set the $_FILES array manually under normal operation (not within our
     * test framework) and that we'll get an error.
     */
    public function testErrorFakeFiles() {
        $this->setUploadFiles('test1.txt');
        $config = $this->config;
        $config['testing'] = false;
        $core = $this->createMockCore($config);
        $core->method('loadModel')->willReturn($this->createMockGradeableList());
        $return = $this->runController($core);
        $this->assertTrue($return['error']);
        $this->assertEquals("The tmp file 'test1.txt' was not properly uploaded.", $return['message']);
        $this->assertFalse($return['success']);
    }

    public function testErrorCreateSVNFile() {
        $_REQUEST['svn_checkout'] = "true";
        FileUtils::createDir(FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser"), null, true);
        FileUtils::createDir(FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser", "1"), 0444);
        $return = $this->runController();
        $this->assertTrue($return['error']);
        $this->assertEquals("Failed to touch file for svn submission.", $return['message']);
        $this->assertFalse($return['success']);
    }

    public function testErrorCreateQueueFile() {
        $this->setUploadFiles('test1.txt');
        $dir = FileUtils::joinPaths($this->config['tmp_path'], "to_be_graded_interactive");
        $this->assertTrue(FileUtils::recursiveRmdir($dir));
        $this->assertTrue(FileUtils::createDir($dir, 0444));
        $return = $this->runController();
        $this->assertTrue($return['error']);
        $this->assertEquals("Failed to create file for grading queue.", $return['message']);
        $this->assertFalse($return['success']);
    }

    public function testErrorBrokenHistoryFile() {
        $tmp = FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser");
        FileUtils::createDir($tmp, null, true);
        file_put_contents(FileUtils::joinPaths($tmp, "user_assignment_settings.json"), "]invalid_json[");
        $this->setUploadFiles('test1.txt');
        $return = $this->runController();
        $this->assertTrue($return['error']);
        $this->assertEquals("Failed to open settings file.", $return['message']);
        $this->assertFalse($return['success']);
    }

    /**
     * We're testing that rolling back the history works on failure to upload the second version of the file
     */
    public function testErrorHistorySecondVersion() {
        $this->setUploadFiles('test1.txt');
        $return = $this->runController();
        $this->assertTrue($return['success']);

        $dir = FileUtils::joinPaths($this->config['tmp_path'], "to_be_graded_interactive");
        $this->assertTrue(FileUtils::recursiveRmdir($dir));
        $this->assertTrue(FileUtils::createDir($dir, 0444));

        $this->setUploadFiles('test1.txt');
        $return = $this->runController();
        $this->assertTrue($return['error']);
        $this->assertEquals("Failed to create file for grading queue.", $return['message']);
        $this->assertFalse($return['success']);

        $tmp = FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser");
        foreach (new \FilesystemIterator($tmp) as $iter) {
            if ($iter->isDir()) {
                $this->assertEquals("1", $iter->getFilename());
            }
            else {
                $this->assertTrue($iter->isFile());
                $this->assertEquals("user_assignment_settings.json", $iter->getFilename());
                $json = FileUtils::readJsonFile($iter->getPathname());
                $this->assertEquals(1, $json['active_version']);
                $this->assertTrue(isset($json['history']));
                $this->assertEquals(1, count($json['history']));
                $this->assertEquals(1, $json['history'][0]['version']);
                $this->assertRegExp('/[0-9]{4}\-[0-1][0-9]\-[0-3][0-9] [0-2][0-9]:[0-5][0-9]:[0-5][0-9]/', $json['history'][0]['time']);
            }
        }
    }

    public function testErrorWriteSettingsFile() {
        $this->setUploadFiles('test1.txt');
        $dir = FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser");
        FileUtils::createDir($dir, null, true);
        $settings = FileUtils::joinPaths($dir, "user_assignment_settings.json");
        file_put_contents($settings, '{"active_version": 0, "history": []}');
        chmod($settings, 0444);
        $return = $this->runController();
        $this->assertTrue($return['error']);
        $this->assertEquals("Failed to write to settings file.", $return['message']);
        $this->assertFalse($return['success']);
        chmod($settings, 0777);
    }

    public function testErrorWriteTimestampFile() {
        $this->setUploadFiles('test1.txt');
        $dir = FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser", "1");
        FileUtils::createDir($dir, null, true);
        $timestamp = FileUtils::joinPaths($dir, ".submit.timestamp");
        file_put_contents($timestamp, "Failed to save timestamp file for this submission.");
        chmod($timestamp, 0444);
        $return = $this->runController();
        $this->assertTrue($return['error']);
        $this->assertEquals("Failed to save timestamp file for this submission.", $return['message']);
        $this->assertFalse($return['success']);
    }

    public function testShowHomeworkPageNoGradeable() {
        $_REQUEST['action'] = 'display';
        $return = $this->runController();
        $this->assertTrue($return['error']);
        $this->assertEquals("No gradeable with that id.", $return['message']);
    }

    public function testShowHomework() {
        $_REQUEST['action'] = 'display';
        $core = $this->createMockCore();
        $now = new \DateTime("now", new \DateTimeZone($core->getConfig()->getTimezone()));
        $gradeable = $this->createMock(Gradeable::class);
        $gradeable->method('hasConfig')->willReturn(true);
        $gradeable->method('getOpenDate')->willReturn($now);

        $g_list = $this->createMock(GradeableList::class);
        $g_list->method('getGradeable')->willReturn($gradeable);
        $core->method('loadModel')->willReturn($g_list);
        $return = $this->runController($core);
        $this->assertEquals("test", $return['id']);
        $this->assertFalse($return['error']);
    }

    public function testShowHomeworkNoConfig() {
        $_REQUEST['action'] = 'display';
        $core = $this->createMockCore();
        $now = new \DateTime("now", new \DateTimeZone($core->getConfig()->getTimezone()));
        $gradeable = $this->createMock(Gradeable::class);
        $gradeable->method('hasConfig')->willReturn(false);
        $gradeable->method('getOpenDate')->willReturn($now);

        $g_list = $this->createMock(GradeableList::class);
        $g_list->method('getGradeable')->willReturn($gradeable);
        $core->method('loadModel')->willReturn($g_list);
        $return = $this->runController($core);
        $this->assertEquals("test", $return['id']);
        $this->assertTrue($return['error']);
    }

    public function testShowHomeworkNoAccess() {
        $_REQUEST['action'] = 'display';
        $core = $this->createMockCore(array(), array('access_grading' => false));
        /** @noinspection PhpUndefinedMethodInspection */
        $now = new \DateTime("tomorrow", new \DateTimeZone($core->getConfig()->getTimezone()));
        $gradeable = $this->createMock(Gradeable::class);
        $gradeable->method('hasConfig')->willReturn(false);
        $gradeable->method('getOpenDate')->willReturn($now);

        $g_list = $this->createMock(GradeableList::class);
        $g_list->method('getGradeable')->willReturn($gradeable);
        $core->method('loadModel')->willReturn($g_list);
        $return = $this->runController($core);
        $this->assertTrue($return['error']);
        $this->assertEquals("No gradeable with that id.", $return['message']);
    }

    public function testUpdateSumbmissionNoId() {
        $_REQUEST['gradeable_id'] = null;
        $_REQUEST['action'] = 'update';
        $return = $this->runController();
        $this->assertTrue($return['error']);
        $this->assertEquals("Invalid gradeable id.", $return['message']);
    }

    public function testUpdateSubmissionNoCsrfToken() {
        $_POST['csrf_token'] = null;
        $_REQUEST['action'] = 'update';
        $return = $this->runController();
        $this->assertTrue($return['error']);
        $this->assertEquals("Invalid CSRF token. Refresh the page and try again.", $return['message']);
    }

    public function testUpdateNegativeVersion() {
        $_REQUEST['action'] = 'update';
        $_REQUEST['new_version'] = -1;
        $return = $this->runController();
        $this->assertTrue($return['error']);
        $this->assertEquals("Cannot set the version below 0.", $return['message']);
    }

    /**
     * @highestVersion 1
     */
    public function testUpdateInvalidVersion() {
        $_REQUEST['action'] = 'update';
        $_REQUEST['new_version'] = 2;
        $return = $this->runController();
        $this->assertTrue($return['error']);
        $this->assertEquals("Cannot set the version past 1.", $return['message']);
    }

    /**
     * @highestVersion 2
     */
    public function testUpdateNoInvalidSettingsFile() {
        $_REQUEST['action'] = 'update';
        $_REQUEST['new_version'] = 1;
        $return = $this->runController();
        $this->assertTrue($return['error']);
        $this->assertEquals("Failed to open settings file.", $return['message']);
    }

    /**
     * @highestVersion 2
     */
    public function testUpdateCannotWriteSettingsFile() {
        $_REQUEST['action'] = 'update';
        $_REQUEST['new_version'] = 1;
        $tmp = FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser");
        FileUtils::createDir($tmp, null, true);
        $json = json_encode(array('active_version' => 1, 'history' => array('version' => 0, 'time' => '')));
        $settings = FileUtils::joinPaths($tmp, "user_assignment_settings.json");
        file_put_contents($settings, $json);
        chmod($settings, 0444);
        $return = $this->runController();
        $this->assertTrue($return['error']);
        $this->assertEquals("Could not write to settings file.", $return['message']);
    }

    public function testUpdateCancelSubmission() {
        $_REQUEST['action'] = 'update';
        $_REQUEST['new_version'] = 0;
        $tmp = FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser");
        FileUtils::createDir($tmp, null, true);
        $json = json_encode(array('active_version' => 1, 'history' => array(array('version' => 0, 'time' => ''))));
        $settings = FileUtils::joinPaths($tmp, "user_assignment_settings.json");
        file_put_contents($settings, $json);
        $return = $this->runController();
        $this->assertFalse($return['error']);
        $this->assertEquals("Cancelled submission for gradeable.", $return['message']);
        $this->assertEquals(0, $return['version']);
        $json = json_decode(file_get_contents($settings), true);
        $this->assertEquals(0, $json['active_version']);
        $this->assertTrue(isset($json['history']));
        $this->assertEquals(2, count($json['history']));
        $this->assertEquals(0, $json['history'][1]['version']);
        $this->assertRegExp('/[0-9]{4}\-[0-1][0-9]\-[0-3][0-9] [0-2][0-9]:[0-5][0-9]:[0-5][0-9]/', $json['history'][1]['time']);
    }

    /**
     * @highestVersion 5
     */
    public function testUpdateSubmission() {
        $_REQUEST['action'] = 'update';
        $_REQUEST['new_version'] = 4;
        $tmp = FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser");
        FileUtils::createDir($tmp, null, true);
        $json = json_encode(array('active_version' => 1, 'history' => array(array('version' => 0, 'time' => ''))));
        $settings = FileUtils::joinPaths($tmp, "user_assignment_settings.json");
        file_put_contents($settings, $json);
        $return = $this->runController();
        $this->assertFalse($return['error']);
        $this->assertEquals("Updated version of gradeable to version #4.", $return['message']);
        $this->assertEquals(4, $return['version']);
        $json = json_decode(file_get_contents($settings), true);
        $this->assertEquals(4, $json['active_version']);
        $this->assertTrue(isset($json['history']));
        $this->assertEquals(2, count($json['history']));
        $this->assertEquals(4, $json['history'][1]['version']);
        $this->assertRegExp('/[0-9]{4}\-[0-1][0-9]\-[0-3][0-9] [0-2][0-9]:[0-5][0-9]:[0-5][0-9]/', $json['history'][1]['time']);
    }

    public function testCheckRefreshSuccess() {
        $_REQUEST['action'] = 'check_refresh';
        $_REQUEST['gradeable_version'] = 1;
        $tmp = FileUtils::joinPaths($this->config['course_path'], "results", "test", "testUser", "1");
        FileUtils::createDir($tmp, null, true);
        touch(FileUtils::joinPaths($tmp, "results.json"));
        $return = $this->runController();
        $this->assertTrue($return['refresh']);
        $this->assertEquals("REFRESH_ME", $return['string']);
    }

    public function testCheckRefreshFailed() {
        $_REQUEST['action'] = 'check_refresh';
        $_REQUEST['gradeable_version'] = 1;
        $return = $this->runController();
        $this->assertFalse($return['refresh']);
        $this->assertEquals("NO_REFRESH", $return['string']);
    }
}
