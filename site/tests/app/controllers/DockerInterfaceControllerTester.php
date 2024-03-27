<?php

namespace tests\app\controllers;

use tests\BaseUnitTest;
use app\controllers\DockerInterfaceController;

class DockerInterfaceControllerTester extends BaseUnitTest {
    private $config;
    private $core;

    public function setUp(): void {
        $config['logged_in'] = true;

        $this->config = $config;
        $user_details = ['access_faculty' => true];
        $this->core = $this->createMockCore($this->config, $user_details);
    }


    public function testShowDockerInterface() {
        $mock_data = [];

        $docker = new DockerInterfaceController($this->core);
        $response = ($docker->showDockerInterface());
        $api = $response->json_response->json;
        $mock_data['autograding_containers'] = false;
        $mock_data['autograding_workers'] = false;
        $mock_data['image_owners'] = [];

        $this->assertTrue($api['status'] === "success");
        $this->assertEquals($mock_data, $api['data']);
    }
}
