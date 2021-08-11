<?php

namespace tests\app\controllers;

use tests\BaseUnitTest;
use app\controllers\DockerInterfaceController;
use app\libraries\FileUtils;
use app\libraries\Utils;
use app\models\Config;

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
        $mock_data = [
            'success' => true,
            'docker_images' => [],
            'docker_info' => []
        ];


        $this->core->expects($this->once())->method("curlRequest")->willReturn(json_encode($mock_data));

        $docker = new DockerInterfaceController($this->core);
        $response = ($docker->showDockerInterface());
        $api = $response->json_response->json;
        $mock_data['autograding_containers'] = false;
        $mock_data['autograding_workers'] = false;

        $this->assertTrue($api['status'] === "success");
        $this->assertEquals($api['data'], $mock_data);
    }
}
