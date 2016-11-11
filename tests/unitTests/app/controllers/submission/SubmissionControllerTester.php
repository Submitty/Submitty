<?php

namespace tests\unitTests\app\controllers\submission;

use app\controllers\student\SubmissionController;
use app\libraries\FileUtils;
use app\libraries\Utils;
use app\models\Gradeable;
use app\models\GradeableList;
use tests\unitTests\BaseUnitTest;

class SubmissionControllerTester extends BaseUnitTest {

    private static $config = array();
    public static function setUpBeforeClass() {
        $_POST['csrf_token'] = null;

        $config['tmp_path'] = Utils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
        $config['semester'] = "test";
        $config['course'] = "test";
        $config['course_path'] = Utils::joinPaths($config['tmp_path'], "courses", $config['semester'],
            $config['course']);

        if (!FileUtils::createDir($config['course_path'], null, true)) {
            print("Could not create ".$config['course_path']);
        }

        if (!FileUtils::createDir(Utils::joinPaths($config['course_path'], "submissions"))) {
            print("Could not create".Utils::joinPaths($config['course_path'], "submissions"));
        }

        static::$config = $config;
    }

    /**
     * Cleanup routine for the tester. This deletes any folders we created in the tmp directory to hold our fake
     * uploaded files.
     */
    public static function tearDownAfterClass() {
        FileUtils::recursiveRmdir(static::$config['tmp_path']);
    }

    /**
     * Because our system does not recursively expand zip files with reckless disregard, we only need to worry
     * about someone hiding a weird size in the root of the zip.
     */
    public function testUploadZipBomb() {
        $_REQUEST['action'] = 'upload';
        $_REQUEST['gradeable_id'] = 'test';
        $_POST['previous_files'] = "";

        $src = Utils::joinPaths(__TEST_DATA__, "files", "zip_bomb.zip");
        $dst = Utils::joinPaths(static::$config['tmp_path'], "zip_bomb.zip");
        copy($src, $dst);
        $_FILES['files1']['name'][] = "zip_bomb.zip";
        $_FILES['files1']['type'][] = "application/zip";
        $_FILES['files1']['size'][] = filesize($dst);
        $_FILES['files1']['tmp_name'][] = $dst;
        $_FILES['files1']['error'][] = null;

        $core = $this->mockCore(static::$config);

        $gradeable = $this->createMock(Gradeable::class);
        $gradeable->method('getId')->willReturn("test");
        $gradeable->method('getName')->willReturn("Test Gradeable");
        $gradeable->method('getHighestVersion')->willReturn(0);
        $gradeable->method('getNumParts')->willReturn(1);
        $gradeable->method('getMaxSize')->willReturn(1000); // 1 MB

        $g_list = $this->createMock(GradeableList::class);
        $g_list->method('getSubmittableElectronicGradeables')->willReturn(array('test' => $gradeable));
        $core->method('loadModel')->willReturn($g_list);
        /** @noinspection PhpParamsInspection */
        $controller = new SubmissionController($core);
        $return = $controller->run();

        $this->assertTrue($return['error'], "An error should have happened");
        $this->assertFalse($return['success']);
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