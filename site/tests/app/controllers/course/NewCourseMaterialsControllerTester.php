<?php

namespace tests\app\controllers\course;

use app\controllers\course\CourseMaterialsController;
use app\libraries\database\DatabaseQueries;
use app\libraries\FileUtils;
use app\libraries\Utils;
use phpmock\phpunit\PHPMock;
use tests\BaseUnitTest;
use ZipArchive;

class NewCourseMaterialsControllerTester extends BaseUnitTest {
    use PHPMock;

    private $core;
    private $config;
    private $upload_path;

    public function setUp(): void {
        $this->config = [];
        $this->config['course_path'] = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
        $this->config['use_mock_time'] = true;
        $_POST['csrf_token'] = "";
        $this->core = $this->createMockCore($this->config);
        $_POST['release_time'] = $this->core->getDateTimeNow()->format("Y-m-d H:i:sO");

        FileUtils::createDir($this->core->getConfig()->getCoursePath() . "/uploads/course_materials", true);
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

        $_POST['requested_path'] = '';
        $_POST['sections_lock'] = false;
        $_POST['hide_from_students'] = false;
        $_POST['sort_priority'] = 0;

        $ret = $controller->ajaxUploadCourseMaterialsFiles();

        $exptected_ret = ['status' => 'success', 'data' => 'Successfully uploaded!'];
        $this->assertEquals($exptected_ret, $ret->json);

        $filename_full = FileUtils::joinPaths($this->upload_path, $name);
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
        $_POST['requested_path'] = '';
        $_POST['sections_lock'] = false;
        $_POST['hide_from_students'] = false;
        $_POST['sort_priority'] = 0;

        $ret = $controller->ajaxUploadCourseMaterialsFiles();
        $exptected_ret = ['status' => 'success', 'data' => 'Successfully uploaded!'];
        $this->assertEquals($exptected_ret, $ret->json);

        $files = FileUtils::getAllFiles($this->upload_path, [], true);
        $this->assertEquals(4, count($files));

        $f1 = Utils::getFirstArrayElement($files);
        $expected_files1 = [
            'name' => 'test0.txt',
            'path' => $this->upload_path . $this->config['course_path'] . '/lev0/test0.txt',
            'size' => 0,
            'relative_name' => ltrim($this->config['course_path'], '/') . '/lev0/test0.txt'
        ];

        $this->assertEquals($expected_files1, $f1);
    }
}
