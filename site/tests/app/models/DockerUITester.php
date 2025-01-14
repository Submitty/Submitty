<?php

namespace tests\app\models;

use app\libraries\Core;
use app\models\DockerUI;
use tests\BaseUnitTest;
use app\libraries\FileUtils;
use app\models\Config;
use app\libraries\Utils;


class DockerUITester extends BaseUnitTest {
    /** Mock core */
    private Core $core;
    /** Tmp area for file operations */
    private string $tmp_dir;

    /** helper functions to test */
    public static function getAutogradingWorkersJson(): string {
        $json = <<<EOD
        {
            "primary": {
                "address": "localhost",
                "capabilities": [
                    "default",
                    "cpp",
                    "python",
                    "et-cetera",
                    "notebook",
                    "taz"
                ],
                "enabled": true,
                "most_recent_tag": "v24.10.00",
                "num_autograding_workers": 5,
                "primary_commit": "8711dfd6a626faf57cc32b108519fa77e23ad9bd",
                "server_name": "vagrant",
                "username": ""
            }
        }
        EOD;

        return $json;
    }

    public static function getAutogradingContainersJson(): string {
        $json = <<<EOD
        {
            "default": [
                "submitty/clang:6.0",
                "submitty/autograding-default:latest",
                "submitty/java:11",
                "submitty/python:3.6",
                "submittyrpi/csci1200:default"
            ],
            "taz": [
                "submittyrpi/csci4510:spring24_java"
            ],
            "et-cetera": [
                "submittyrpi/csci1200:default",
                "submitty/python:latest"
            ],
            "cpp": [
                "submitty/python:latest"
            ]
        }
        EOD;

        return $json;
    }

    /** Setup runs before each unit test in this file */
    public function setUp(): void {
        $this->tmp_dir = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
        //create dummy log areas
        FileUtils::createDir(FileUtils::joinPaths($this->tmp_dir, "logs", "docker"), true);
        FileUtils::createDir(FileUtils::joinPaths($this->tmp_dir, "logs", "sysinfo"), true);

        $this->core = $this->createMockModel(Core::class);
        $config = $this->createMockModel(Config::class);
        $config->method('getSubmittyPath')->willReturn($this->tmp_dir);
        $this->core->method('getConfig')->willReturn($config);
    }

    /** tearDown runs after each unit test in this file */
    public function tearDown(): void {
        if (file_exists($this->tmp_dir)) {
            FileUtils::recursiveRmdir($this->tmp_dir);
        }
    }

    /** begin unit tests */
    public function testConstructorGoodData() {
        $docker_ui = new DockerUI($this->core, [
            "autograding_containers" => json_decode($this::getAutogradingContainersJson(), true), 
            "autograding_workers" => json_decode($this::getAutogradingWorkersJson(), true),
            "image_owners" => [],
        ]);

        $this->assertEquals(0, count($docker_ui->getErrorLogs()));
    }

}
