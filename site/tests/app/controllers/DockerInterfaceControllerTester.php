<?php

namespace tests\app\controllers;

use tests\BaseUnitTest;
use app\libraries\Utils;
use app\libraries\FileUtils;
use app\controllers\DockerInterfaceController;
use tests\app\models\DockerUITester;

class DockerInterfaceControllerTester extends BaseUnitTest {
    private $core;
    /** Tmp area for file operations */
    private string $tmp_dir;

    public function setUp(): void {
        $user_details = ['access_faculty' => true];
        $this->core = $this->createMockCore([], $user_details);

        $this->tmp_dir = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
        //create dummy log areas
        FileUtils::createDir(FileUtils::joinPaths($this->tmp_dir, "config"), true);
        FileUtils::createDir(FileUtils::joinPaths($this->tmp_dir, "logs", "docker"), true);
        FileUtils::createDir(FileUtils::joinPaths($this->tmp_dir, "logs", "sysinfo"), true);

        FileUtils::writeFile(
            FileUtils::joinPaths($this->tmp_dir, "config", "autograding_containers.json"),
            DockerUITester::getAutogradingContainersJson()
        );
        FileUtils::writeFile(
            FileUtils::joinPaths($this->tmp_dir, "config", "autograding_workers.json"),
            DockerUITester::getAutogradingWorkersJson()
        );

        /** Set dummy paths to tmp dir, ok to use same path since everything will be in dirs inside of there */
        $this->core->getConfig()->method('getSubmittyPath')->willReturn($this->tmp_dir);
        $this->core->getConfig()->method('getSubmittyInstallPath')->willReturn($this->tmp_dir);
    }

    /** tearDown runs after each unit test in this file */
    public function tearDown(): void {
        if (file_exists($this->tmp_dir)) {
            FileUtils::recursiveRmdir($this->tmp_dir);
        }
    }

    public function testShowDockerInterface() {
        $mock_data = [];

        $docker = new DockerInterfaceController($this->core);
        $response = ($docker->showDockerInterface());
        $api = $response->json_response->json;
        $mock_data['autograding_containers'] = json_decode(DockerUITester::getAutogradingContainersJson(), true);
        $mock_data['autograding_workers'] = json_decode(DockerUITester::getAutogradingWorkersJson(), true);
        $mock_data['image_owners'] = [];

        $this->assertTrue($api['status'] === "success");
        $this->assertEquals($mock_data, $api['data']);
    }
}
