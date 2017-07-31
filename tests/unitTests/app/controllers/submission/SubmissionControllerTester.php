<?php

namespace tests\unitTests\app\controllers\submission;

use \ZipArchive;
use app\controllers\student\SubmissionController;
use app\exceptions\IOException;
use app\libraries\FileUtils;
use app\libraries\Utils;
use app\models\Gradeable;
use app\models\GradeableList;
use tests\unitTests\BaseUnitTest;
use app\models\User;

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
        $_POST['user_id'] = "testUser";

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

    protected function createMockUser($id) {
        $return = $this->createMockModel(User::class);
        $return->method("getId")->willReturn($id);
        return $return;
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
        $gradeable = $this->createMockModel(Gradeable::class);
        $gradeable->method('getId')->willReturn("test");
        $gradeable->method('getName')->willReturn("Test Gradeable");
        // $gradeable->method('getUser')->willReturn("testUser");
        $gradeable->method('getUser')->willReturn($this->createMockUser('testUser'));

        $gradeable->method('getHighestVersion')->willReturn(intval($highest_version));
        $gradeable->method('getNumParts')->willReturn(intval($num_parts));
        $gradeable->method('getMaxSize')->willReturn($max_size);

        $g_list = $this->createMockModel(GradeableList::class);
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
     * Creates a file with teh given contents to be used to upload for a specified part.
     *
     * @param string $filename
     * @param string $content
     * @param int    $part
     */
    private function addUploadFile($filename, $content="", $part=1) {
        FileUtils::createDir(FileUtils::joinPaths($this->config['tmp_path'], 'files', 'part'.$part), 0777, true);
        $filepath = FileUtils::joinPaths($this->config['tmp_path'], 'files', 'part'.$part, $filename);
        if (file_put_contents($filepath, $content) === false) {
            throw new IOException("Could not write file to {$filepath}");
        }
        $_FILES["files{$part}"]['name'][] = $filename;
        $_FILES["files{$part}"]['type'][] = FileUtils::getMimeType($filepath);
        $_FILES["files{$part}"]['size'][] = filesize($filepath);
        $_FILES["files{$part}"]['tmp_name'][] = $filepath;
        $_FILES["files{$part}"]['error'][] = null;
    }

    /**
     * Given an array, this will create a zip file using the given name (appending .zip to it) with
     * the $files array describing what will go into the zip, filling out the archive by calling the
     * createZip function recursively.
     *
     * @param string $zip_name
     * @param array  $files
     * @param int    $part
     */
    private function addUploadZip($zip_name, $files, $part=1) {
        $part_path = FileUtils::joinPaths($this->config['tmp_path'], 'files', 'part'.$part);
        $root_path = FileUtils::joinPaths($part_path, $zip_name);
        FileUtils::createDir($root_path, 0777, true);
        $zip_path =  FileUtils::joinPaths($part_path, $zip_name.'.zip');
        $zip = new ZipArchive();
        $zip->open($zip_path, ZipArchive::CREATE || ZipArchive::OVERWRITE);
        $this->createZip($files, $zip, $root_path);
        $zip->close();
        $_FILES["files{$part}"]['name'][] = $zip_name.'.zip';
        $_FILES["files{$part}"]['type'][] = FileUtils::getMimeType($zip_path);
        $_FILES["files{$part}"]['size'][] = filesize($zip_path);
        $_FILES["files{$part}"]['tmp_name'][] = $zip_path;
        $_FILES["files{$part}"]['error'][] = null;
    }

    /**
     * This recursive function fills out a zip archive from the given $files array. For each element in the array,
     * if the value is an array, then the key is a folder name and the value describes the contents of that folder,
     * wherein we create that folder and then recursively call this function for the files in the directory. Otherwise
     * if they key is numeric, then the value is the filename and the file is empty or the key is the filename and the
     * value is its contents. However, if the filename ends with '.zip', then we create a zip archive (containing one
     * empty file) to be put into the zip.
     *
     * @param array       $files
     * @param ZipArchive  $zip
     * @param string      $dir
     * @param string|null $root_dir
     */
    private function createZip($files, $zip, $dir, $root_dir=null) {
        if ($root_dir === null) {
            $root_dir = $dir;
        }
        foreach ($files as $key => $value) {
            if (is_array($value)) {
                $new_dir = FileUtils::joinPaths($dir, $key);
                FileUtils::createDir($new_dir);
                $this->createZip($value, $zip, $new_dir, $root_dir);
            }
            else {
                if (is_numeric($key)) {
                    $filename = $value;
                    $content = "";
                }
                else {
                    $filename = $key;
                    $content = $value;
                }
                $file_path = FileUtils::joinPaths($dir, $filename);
                if (Utils::endsWith($filename, '.zip') === true) {
                    $file = new ZipArchive();
                    $file->open($file_path, ZipArchive::CREATE || ZipArchive::OVERWRITE);
                    $file->addFromString('test1.txt', 'a');
                    $file->close();
                }
                else {
                    file_put_contents($file_path, $content);
                }
                $zip->addFile($file_path, substr($file_path, strlen($root_dir) + 1));
            }
        }
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
        $this->addUploadFile('test1.txt', 'a');
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
        $this->addUploadFile('test1.txt', 'a');
        $this->addUploadFile('test2.txt', 'b');
        $this->addUploadFile('test2.txt', 'c', 2);
        $return = $this->runController();
        $this->assertFalse($return['error'], "Error thrown: {$return['message']}");
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
                $this->assertEquals(".submit.timestamp", $iter->getFilename());
            }
            else if ($iter->isDir()) {
                $this->assertTrue(in_array($iter->getFilename(), array('part1', 'part2')));
                $files[$iter->getFilename()] = array();
                $iter2 = $iter->getChildren();
                while ($iter2 !== "" && $iter2->getFilename() !== "") {
                    if ($iter2->isDot()) {
                        $iter2->next();
                        continue;
                    }
                    else if ($iter2->isFile()) {
                        $files[$iter->getFilename()][$iter2->getFilename()] = file_get_contents($iter2->getPathname());
                    }
                    else {
                        $this->fail("Part directory should not contain a directory.");
                    }
                    $iter2->next();
                }
            }
            else {
                $this->fail("Unknown type found in test directory.");
            }
            $iter->next();
        }
        ksort($files);
        $expected = array(
            'part1' => array(
                'test1.txt' => 'a',
                'test2.txt' => 'b',
            ),
            'part2' => array(
                'test2.txt' => 'c'
            )
        );
        $this->assertEquals($expected, $files);
    }

    /**
     * Test what happens if we're uploading a zip that contains a directory.
     */
    public function testZipWithDirectory() {
        $zip = array(
            'testDir' => array(
                'test1.txt' => ''
            ),
            'test2.txt' => ''
        );
        $this->addUploadZip('directory_inside', $zip);
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
        $this->addUploadFile('test1.txt');
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

        $this->addUploadFile('test2.txt');
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
        $this->addUploadFile("test1.txt", "", 1);
        $this->addUploadFile("test1.txt", "", 2);
        $return = $this->runController();
        $this->assertFalse($return['error'], "Error: {$return['message']}");
        $this->assertTrue($return['success']);

        $_POST['previous_files'] = json_encode(array(0 => array('test1.txt'), 1 => array('test1.txt')));
        $core = $this->createMockCore($this->config);
        $core->method('loadModel')->willReturn($this->createMockGradeableList(1, 2));
        $this->addUploadFile("test2.txt", "", 1);
        $this->addUploadFile("test2.txt", "", 2);
        $return = $this->runController($core);
        $this->assertFalse($return['error'], "Error: {$return['message']}");
        $this->assertTrue($return['success']);
    }

    /**
     * Upload a second version of a gradeable that includes previous files, but there's no overlap in file names
     * so we should have one file in version 1 and two files in version 2
     */
    public function testSecondVersionPreviousNoOverlap() {
        $this->addUploadFile('test1.txt');
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

        $this->addUploadFile('test2.txt');
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
        $this->addUploadFile('test1.txt', 'old_file');
        $return = $this->runController();
        $this->assertFalse($return['error'], "Error: {$return['message']}");
        $this->assertTrue($return['success']);
        $tmp = FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser", "1");
        $files = array();
        foreach (new \FilesystemIterator($tmp) as $file) {
            $this->assertFalse($file->isDir());
            $files[] = $file->getFilename();
            if ($file->getFilename() === "test1.txt") {
                $this->assertStringEqualsFile($file->getPathname(), "old_file");
            }
        }
        sort($files);
        $this->assertEquals(array('.submit.timestamp', 'test1.txt'), $files);

        $this->addUploadFile('test1.txt', 'new_file');
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
        $this->addUploadFile('test1.txt', 'old_file');
        $return = $this->runController();
        $this->assertFalse($return['error'], "Error: {$return['message']}");
        $this->assertTrue($return['success']);
        $tmp = FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser", "1");
        $files = array();
        foreach (new \FilesystemIterator($tmp) as $file) {
            $this->assertFalse($file->isDir());
            $files[] = $file->getFilename();
            if ($file->getFilename() === "test1.txt") {
                $this->assertStringEqualsFile($file->getPathname(), "old_file");
            }
        }
        sort($files);
        $this->assertEquals(array('.submit.timestamp', 'test1.txt'), $files);

        $this->addUploadZip('overlap', array('test1.txt' => 'new_file'));
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
        $zip = array(
            'test1.txt' => 'a',
            'basic_zip.zip'
        );
        $this->addUploadZip('zip_inside', $zip);
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
        $this->addUploadZip('zippedfiles', array('test.txt' => 'zip_file', 'test2.txt' => 'zip_file2'));
        $this->addUploadFile('test.txt', 'non_zip_file');

        $return = $this->runController();

        $this->assertFalse($return['error'], "Error: {$return['message']}");
        $this->assertTrue($return['success']);

        $tmp = FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser", "1");
        $this->assertStringEqualsFile(FileUtils::joinPaths($tmp, "test.txt"), "non_zip_file");
        $this->assertStringEqualsFile(FileUtils::joinPaths($tmp, "test2.txt"), "zip_file2");
    }

    /**
     * This tests the same thing as testSameFilenameInZip(), however we submit "test.txt" before "zippedfiles.zip"
     */
    public function testSameFilenameInZipReversed() {
        $this->addUploadFile('test.txt', 'non_zip_file');
        $this->addUploadZip('zippedfiles', array('test.txt' => 'zip_file', 'test2.txt' => 'zip_file2'));

        $return = $this->runController();

        $this->assertFalse($return['error'], "Error: {$return['message']}");
        $this->assertTrue($return['success']);

        $tmp = FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser", "1");
        $this->assertStringEqualsFile(FileUtils::joinPaths($tmp, "test.txt"), "zip_file");
        $this->assertStringEqualsFile(FileUtils::joinPaths($tmp, "test2.txt"), "zip_file2");
        $files = array();
        foreach (new \FilesystemIterator($tmp) as $iter) {
            $this->assertTrue($iter->isFile());
            $files[] = $iter->getFilename();
        }
        sort($files);
        $this->assertEquals(array('.submit.timestamp', 'test.txt', 'test2.txt'), $files);

    }

    public function testFilenameWithSpaces() {
        $this->addUploadFile("filename with spaces.txt");
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
        $zip = array(
            'folder with spaces' => array('filename with spaces2.txt'),
            'filename with spaces.txt'
        );
        $this->addUploadZip('contains_spaces', $zip);
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
                    $this->assertEquals("filename with spaces2.txt", $iter2->getFilename());
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
        $this->addUploadFile('test1.txt');
        $return = $this->runController();
        $this->assertTrue($return['success']);

        $_POST['previous_files'] = json_encode(array(0 => array('missing.txt')));
        $this->addUploadFile('test1.txt');
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
        $this->addUploadFile('in"valid.txt');
        $return = $this->runController();
        $this->assertTrue($return['error']);
        $this->assertEquals("Error: You may not use quotes, backslashes or angle brackets in your file name in\"valid.txt.", $return['message']);
        $this->assertFalse($return['success']);
    }

    public function testInvalidFilenameInZip() {
        $this->addUploadZip("invalid", array('in"valid.txt'));
        $return = $this->runController();
        $this->assertTrue($return['error']);
        $this->assertEquals("Error: You may not use quotes, backslashes or angle brackets in your filename for files inside invalid.zip.", $return['message']);
        $this->assertFalse($return['success']);
    }

    /**
     * @maxSize 0
     */
    public function testErrorFileTooBig() {
        $this->addUploadFile('test1.txt', 'a');
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
        $this->addUploadZip('zip_bomb', array('bomb.txt' => str_repeat('01', 5120000)));
        $return = $this->runController();

        $this->assertTrue($return['error'], "An error should have happened");
        $this->assertEquals("File(s) uploaded too large.  Maximum size is 1000 kb. Uploaded file(s) was 10240 kb.",
            $return['message']);
        $this->assertFalse($return['success']);
        $tmp = FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser", "1");
        $this->assertFalse(is_dir($tmp));
    }

    public function testErrorOnBrokenZip() {
        $this->addUploadZip('broken', array('test1.txt'));
        $path = FileUtils::joinPaths($this->config['tmp_path'], 'files', 'part1', 'broken.zip');
        $fh = fopen($path, 'r+') or die("can't open file");
        $stat = fstat($fh);
        ftruncate($fh, $stat['size']-1);
        fclose($fh);
        $return = $this->runController();
        $this->assertTrue($return['error']);
        $this->assertEquals("Could not properly unpack zip file. Error message: Invalid or uninitialized Zip object.", $return['message']);
        $this->assertFalse($return['success']);
    }

    public function testErrorOnCopyingPrevious() {
        $this->addUploadFile('test1.txt');
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
        $this->addUploadFile('test1.txt');
        FileUtils::createDir(FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser"), null, true);
        FileUtils::createDir(FileUtils::joinPaths($this->config['course_path'], "submissions", "test", "testUser", "1"), 0444);
        $return = $this->runController();
        $this->assertTrue($return['error']);
        $this->assertEquals("Failed to copy uploaded file test1.txt to current submission.", $return['message']);
        $this->assertFalse($return['success']);
    }

    public function testErrorCleanupTempFiles() {
        $dst_dir = FileUtils::joinPaths($this->config['tmp_path'], "files");
        $dst_file = FileUtils::joinPaths($dst_dir, "test.txt");
        FileUtils::createDir($dst_dir);
        file_put_contents($dst_file, "a");
        $_FILES["files1"]['name'][] = "test1.txt";
        $_FILES["files1"]['type'][] = FileUtils::getMimeType($dst_file);
        $_FILES["files1"]['size'][] = filesize($dst_file);
        $_FILES["files1"]['tmp_name'][] = $dst_file;
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
        $this->addUploadFile('test1.txt');
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
        $this->addUploadFile('test1.txt');
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
        $this->addUploadFile('test1.txt');
        $return = $this->runController();
        $this->assertTrue($return['error']);
        $this->assertEquals("Failed to open settings file.", $return['message']);
        $this->assertFalse($return['success']);
    }

    /**
     * We're testing that rolling back the history works on failure to upload the second version of the file
     */
    public function testErrorHistorySecondVersion() {
        $this->addUploadFile('test1.txt');
        $return = $this->runController();
        $this->assertTrue($return['success']);

        $dir = FileUtils::joinPaths($this->config['tmp_path'], "to_be_graded_interactive");
        $this->assertTrue(FileUtils::recursiveRmdir($dir));
        $this->assertTrue(FileUtils::createDir($dir, 0444));

        $this->addUploadFile('test1.txt');
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
        $this->addUploadFile('test1.txt');
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
        $this->addUploadFile('test1.txt');
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
        $now = new \DateTime("now", $core->getConfig()->getTimezone());
        $gradeable = $this->createMockModel(Gradeable::class);
        $gradeable->method('hasConfig')->willReturn(true);
        $gradeable->method('getOpenDate')->willReturn($now);
        $gradeable->method('getUser')->willReturn($this->createMockUser('testUser'));

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
        $now = new \DateTime("now", $core->getConfig()->getTimezone());
        $gradeable = $this->createMockModel(Gradeable::class);
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
        $now = new \DateTime("tomorrow", $core->getConfig()->getTimezone());
        $gradeable = $this->createMockModel(Gradeable::class);
        $gradeable->method('hasConfig')->willReturn(false);
        $gradeable->method('getOpenDate')->willReturn($now);

        $g_list = $this->createMockModel(GradeableList::class);
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
