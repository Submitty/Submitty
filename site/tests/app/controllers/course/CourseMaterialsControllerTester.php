<?php

namespace tests\app\controllers\course;

use tests\BaseUnitTest;
use app\controllers\course\CourseMaterialsController;
use app\libraries\FileUtils;
use app\libraries\Utils;
use ZipArchive;

class CourseMaterialsControllerTester extends BaseUnitTest {
    use \phpmock\phpunit\PHPMock;

    private $core;
    private $config;
    private $json_path;
    private $upload_path;

    public function setUp(): void {
        $this->config = [];
        $this->config['course_path'] = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
        $this->config['use_mock_time'] = true;
        $_POST['csrf_token'] = "";
        $this->core = $this->createMockCore($this->config);
        $_POST['release_time'] = $this->core->getDateTimeNow()->format("Y-m-d H:i:sO");

        FileUtils::createDir($this->core->getConfig()->getCoursePath() . "/uploads/course_materials", true);
        $this->json_path = $this->core->getConfig()->getCoursePath() . '/uploads/course_materials_file_data.json';
        $this->upload_path = $this->core->getConfig()->getCoursePath() . "/uploads/course_materials";
    }

    public function tearDown(): void {
        FileUtils::recursiveRmdir($this->config['course_path']);
        $_POST = [];
        $_FILES = [];
    }

    private function buildFakeFile($filename, $part = 1) {
        $_FILES["files{$part}"]['name'][] = $filename;
        $_FILES["files{$part}"]['type'][] = mime_content_type($this->upload_path . "/" . $filename);
        $_FILES["files{$part}"]['size'][] = filesize($this->upload_path . "/" . $filename);

        $tmpname = $this->upload_path . "/" . Utils::generateRandomString() . $filename;
        copy($this->upload_path . "/" . $filename, $tmpname);

        $_FILES["files{$part}"]['tmp_name'][] = $tmpname;
        $_FILES["files{$part}"]['error'][] = 0;
    }

    private function buildFakeZipFile($name, $part = 1, $num_files = 1, $depth = 1) {
        $zip = new ZipArchive();

        $filename_full = FileUtils::joinPaths($this->config['course_path'], $name);
        $files = [];
        if ($zip->open($filename_full, ZipArchive::CREATE) === true) {
            $lev = "";
            for ($i = 0; $i < $depth; $i++) {
                for ($j = 0; $j < $num_files; $j++) {
                    $fname = "test" . $j . ".txt";
                    $tmpfile = fopen($this->config['course_path'] . $lev . "/" . $fname, "w");
                    $zip->addFile($this->config['course_path'] . $lev . "/" . $fname);
                    $files[] = $this->config['course_path'] . $lev . "/" . $fname;
                }
                $lev .= "/lev" . $i . "";
                FileUtils::createDir($this->config['course_path'] . $lev);
            }

            $zip->close();
        }

        $_FILES["files{$part}"]['name'][] = $name;
        $_FILES["files{$part}"]['type'][] = mime_content_type($filename_full);
        $_FILES["files{$part}"]['size'][] = filesize($this->config['course_path'] . "/" .   $name);

        $tmpname = $this->config['course_path'] . "/" . Utils::generateRandomString() . $name;
        copy($this->config['course_path'] . "/" . $name, $tmpname);

        $_FILES["files{$part}"]['tmp_name'][] = $tmpname;
        $_FILES["files{$part}"]['error'][] = 0;

        return $files;
    }

    public function testCourseMaterialsUpload() {
        $this->getFunctionMock('app\controllers\course', 'is_uploaded_file')
            ->expects($this->any())
            ->willReturn(true);

        $controller = new CourseMaterialsController($this->core);

        $name = "foo.txt";
        file_put_contents($this->upload_path . "/" .  $name, 'a');
        $this->buildFakeFile($name);

        $ret = $controller->ajaxUploadCourseMaterialsFiles();

        $json = FileUtils::readJsonFile($this->json_path);
        //we need to check that the file exists in the correct folder and also the JSON file
        $filename_full = FileUtils::joinPaths($this->upload_path, $name);
        $expected_json = [
            $filename_full => [
                "release_datetime" => $_POST['release_time'],
                'hide_from_students' => null,
                'external_link' => false,
                'sort_priority' => 0
            ]
        ];
        $this->assertEquals($expected_json, $json);
        //check the uploads directory now
        $files = FileUtils::getAllFiles($this->upload_path, [], true);

        $expected_files = [
            $name => [
                'name' => $name,
                'path' => $filename_full,
                'size' => filesize($filename_full),
                'relative_name' => $name
            ]
        ];

        $this->assertEquals($expected_files, $files);
    }

    public function testZipCourseUpload() {
        $this->getFunctionMock('app\controllers\course', 'is_uploaded_file')
            ->expects($this->any())
            ->willReturn(true);

        $controller = new CourseMaterialsController($this->core);

        $_FILES = [];
        $_POST['expand_zip'] = 'on';
        //create a zip file of depth = 2 with 2 files in each level.
        $fake_files = $this->buildFakeZipFile('foo.zip', 1, 2, 2);
        $ret = $controller->ajaxUploadCourseMaterialsFiles();
        $json = FileUtils::readJsonFile($this->json_path);

        $expected_ret = ['status' => 'success', 'data' => 'Successfully uploaded!'];
        $this->assertEquals($expected_ret, $ret);

        $files = FileUtils::getAllFiles($this->upload_path, [], true);
        $this->assertEquals(4, count($files));

        $f1 = Utils::getFirstArrayElement($files);
        $keys =     array_keys($json);

        $expected_json1 = [
            'release_datetime' => $_POST['release_time'],
            'hide_from_students' => null,
            'external_link' => false,
            'sort_priority' => 0
        ];

        $this->assertEquals($expected_json1, $json[$keys[1]]);
        $expected_files1 = [
            'name' => 'test0.txt',
            'path' => $this->upload_path . $this->config['course_path'] . '/lev0/test0.txt',
            'size' => 0,
            'relative_name' => ltrim($this->config['course_path'], '/') . '/lev0/test0.txt'
        ];

        $this->assertEquals($expected_files1, $f1);
    }

    /**
     * @runInSeparateProcess
     */
    public function testModifyCourseMaterials() {
        $this->getFunctionMock('app\controllers\course', 'is_uploaded_file')
            ->expects($this->any())
            ->willReturn(true);
        $controller = new CourseMaterialsController($this->core);

        $_FILES = [];

        //create a file
        $name = "foo.txt";
        file_put_contents($this->upload_path . "/" .  $name, 'a');
        $this->buildFakeFile($name);

        //'upload it'
        $ret = $controller->ajaxUploadCourseMaterialsFiles();
        $json = FileUtils::readJsonFile($this->json_path);

        $expected_json = [
            $this->upload_path . "/" . $name => [
                'release_datetime' => $_POST['release_time'],
                'hide_from_students' => null,
                'external_link' => false,
                'sort_priority' => 0
            ]
        ];

        $this->assertEquals($expected_json, $json);

        $_POST['fn'][] = FileUtils::joinPaths($this->upload_path, $name);
        $new_date = new \DateTime('2005-01-01');
        $new_date = $new_date->format('Y-m-d H:i:sO');

        $ret = $controller->modifyCourseMaterialsFileTimeStamp($_POST['fn'][0], $new_date);

        $this->assertEquals(['status' => 'success', 'data' => 'Time successfully set.'], $ret);
        $json = FileUtils::readJsonFile($this->json_path);

        //check the date has been updated to the new time
        $expected_json = [
            $this->upload_path . "/" . $name => [
                'release_datetime' => $new_date,
                'hide_from_students' => null,
                'external_link' => false,
                'sort_priority' => 0
            ]
        ];

        $this->assertEquals($expected_json, $json);

        $_FILES = [];
        //try multiple
        //create a file
        $name = "foo2.txt";
        file_put_contents($this->upload_path . "/" .  $name, 'a');
        $this->buildFakeFile($name);

        //'upload it'
        $ret = $controller->ajaxUploadCourseMaterialsFiles();

        $_POST['fn'][] = FileUtils::joinPaths($this->upload_path, $name);
        $ret = $controller->modifyCourseMaterialsFileTimeStamp($_POST['fn'], $new_date);

        $json = FileUtils::readJsonFile($this->json_path);
        $this->assertEquals(2, count($json));   //2 files

        $expected_json2 = [
            'release_datetime' => $new_date,
            'hide_from_students' => null,
            'external_link' => false,
            'sort_priority' => 0
        ];
        $this->assertEquals($expected_json2, $json[$_POST['fn'][1]]);
    }

    /**
     * @runInSeparateProcess
     */
    public function testDeleteCourseMaterial() {
        $this->getFunctionMock('app\controllers\course', 'is_uploaded_file')
            ->expects($this->any())
            ->willReturn(true);

        $controller = new CourseMaterialsController($this->core);

        $_FILES = [];

        //create a file
        $name = "foo.txt";
        file_put_contents($this->upload_path . "/" .  $name, 'a');
        $this->buildFakeFile($name);
        //'upload it'
        $ret = $controller->ajaxUploadCourseMaterialsFiles();

        $dir = 'course_materials';
        $path = $this->upload_path . "/" . $name;

        $this->core->getAccess()->method('resolveDirPath')->willReturn($path);
        $controller->deleteCourseMaterial($this->upload_path . "/" . $name);

        //check that the file no longer exists in the path and json file
        $json = FileUtils::readJsonFile($this->json_path);
        $this->assertEquals([], $json);

        $files = FileUtils::getAllFiles($this->upload_path);
        $this->assertEquals(0, count($files));
    }

    public function testRequestedPathUpload() {
         $this->getFunctionMock('app\controllers\course', 'is_uploaded_file')
            ->expects($this->any())
            ->willReturn(true);

        $controller = new CourseMaterialsController($this->core);
        $_FILES = [];
        $_POST['requested_path'] = 'foo/foo2';

        $name = "foo.txt";
        file_put_contents($this->upload_path . "/" .  $name, 'a');
        $this->buildFakeFile($name);

        $ret = $controller->ajaxUploadCourseMaterialsFiles();
        $json = FileUtils::readJsonFile($this->json_path);

        $filename_full = FileUtils::joinPaths($this->upload_path, "foo/foo2", $name);
        $expected_json = [
            $filename_full => [
                "release_datetime" => $_POST['release_time'],
                "hide_from_students" => null,
                'external_link' => false,
                'sort_priority' => 0
            ]
        ];

        $this->assertEquals($expected_json, $json);
    }
}
