<?php

namespace tests\app\controllers;

use tests\BaseUnitTest;
use app\controllers\DockerInterfaceController;
use app\libraries\FileUtils;
use app\libraries\Utils;

class DockerInterfaceControllerTester extends BaseUnitTest {
    private $config;
    private $core;

    public function setUp(): void {
        $config['tmp_path'] = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
        $config['job_path'] = FileUtils::joinPaths($config['tmp_path'], 'daemon_job_queue');
        $config['docker_data'] = FileUtils::joinPaths($config['tmp_path'], 'docker_data');
        $config['logged_in'] = true;

        $this->config = $config;
        $user_details = ['access_faculty' => true];
        $this->core = $this->createMockCore($this->config, $user_details);

        FileUtils::createDir($config['tmp_path']);
        FileUtils::createDir($config['job_path']);
        FileUtils::createDir($config['docker_data']);
    }

    public function tearDown(): void {
        $this->assertTrue(FileUtils::recursiveRmdir($this->config['tmp_path']));
    }


    public function testUpdateDockerData() {

        $docker = new DockerInterfaceController($this->core);
        $response = ($docker->updateDockerData())->json_response->json;

        //test function response & if the job file has been created
        $this->assertEquals(['status' => 'success', 'data' => null], $response);
        $this->assertTrue(file_exists(FileUtils::joinPaths($this->config['job_path'], 'updateDockerInfo.json')));
    }


    public function testCheckJobStatus() {
        $docker = new DockerInterfaceController($this->core);
        $response = ($docker->checkJobStatus())->json_response->json;

        $this->assertEquals(['status' => 'success', 'data' => ['found' => false]], $response);
        //create the file and check again
        $docker->updateDockerData();
        $response = ($docker->checkJobStatus())->json_response->json;
        $this->assertEquals(['status' => 'success', 'data' => ['found' => true]], $response);
    }

    /**
     * @runInSeparateProcess
     */
    public function testShowDockerInterface() {
        $docker = new DockerInterfaceController($this->core);
        $response = ($docker->showDockerInterface());

        //test api response
        $api = $response->json_response->json;
        $this->assertEquals(['status' => 'fail', 'message' => "Failed to parse submitty docker information"], $api);

        //create the file
        $tgt_file = FileUtils::joinPaths($this->config['docker_data'], 'submitty_docker.json');
        file_put_contents($tgt_file, '{"test": "test"}');

        $response = ($docker->showDockerInterface());
        $api = $response->json_response->json;
        $this->assertEquals(['status' => 'success', 'data' => ['test' => 'test']], $api);
    }
}
