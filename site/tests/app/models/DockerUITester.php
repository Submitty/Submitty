<?php

namespace tests\app\models;

use app\libraries\Core;
use app\models\DockerUI;
use tests\BaseUnitTest;
use app\libraries\FileUtils;
use app\libraries\Utils;

class DockerUITester extends BaseUnitTest {
    /** Mock core */
    private Core $core;
    /** Tmp area for file operations */
    private string $tmp_dir;
    /** Example log file from the docker update job */
    private string $docker_job_log_file;
    /** Example log file from the sysinfo update job */
    private string $sysinfo_log_file;

    /** helper functions to test */
    public static function getAutogradingWorkersJson(): string {
        return <<<EOD
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
    }

    public static function getAutogradingContainersJson(): string {
        return <<<EOD
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
    }

    /** Setup runs before each unit test in this file */
    public function setUp(): void {
        $this->tmp_dir = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
        //create dummy log areas
        FileUtils::createDir(FileUtils::joinPaths($this->tmp_dir, "logs", "docker"), true);
        FileUtils::createDir(FileUtils::joinPaths($this->tmp_dir, "logs", "sysinfo"), true);

        $this->docker_job_log_file = FileUtils::joinPaths(dirname(__DIR__, 3), 'tests', 'data', 'logs', 'docker', 'install_containers.txt');
        $this->sysinfo_log_file = FileUtils::joinPaths(dirname(__DIR__, 3), 'tests', 'data', 'logs', 'docker', 'sysinfo.txt');

        $this->core = $this->createMockCore([
            'tmp_path' => $this->tmp_dir
        ]);
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
        $this->assertEquals(json_decode($this::getAutogradingContainersJson(), true), $docker_ui->getAutogradingContainers());
        $this->assertEquals([], $docker_ui->getDockerImageOwners());
    }

    public function testParsingLogLines() {
        $this->assertFileExists($this->docker_job_log_file);
        $this->assertFileExists($this->sysinfo_log_file);

        //place some dummy data first
        FileUtils::writeFile(FileUtils::joinPaths($this->tmp_dir, "logs", "docker", "20251234.txt"), file_get_contents($this->docker_job_log_file));
        FileUtils::writeFile(FileUtils::joinPaths($this->tmp_dir, "logs", "sysinfo", "20251234.txt"), file_get_contents($this->sysinfo_log_file));

        $docker_ui = new DockerUI($this->core, [
            "autograding_containers" => json_decode($this::getAutogradingContainersJson(), true),
            "autograding_workers" => json_decode($this::getAutogradingWorkersJson(), true),
            "image_owners" => [],
        ]);

        $this->assertEquals(6, count($docker_ui->getCapabilities()));
        $this->assertEquals(1, count($docker_ui->getWorkerMachines()));

        $worker = $docker_ui->getWorkerMachines()[0];
        $this->assertEquals("primary", $worker->name);
        $this->assertEquals(5, $worker->num_autograding_workers);
        $this->assertEquals(["default", "cpp", "python", "et-cetera", "notebook", "taz"], $worker->capabilities);
        $this->assertEquals(true, $worker->is_enabled);
        $this->assertEquals(false, $worker->failed_to_update);

        $this->assertEquals(11, count($docker_ui->getDockerImages()));
        $image = $docker_ui->getDockerImages()[0];
        $this->assertEquals("submitty/tutorial:database_client", $image->primary_name);

        $image = $docker_ui->getDockerImages()[2];
        $this->assertEquals("ubuntu:custom", $image->primary_name);
        $this->assertEquals(1, count($image->aliases));
        $this->assertEquals("submitty/autograding-default:latest", $image->aliases[0]);

        $info = $worker->system_information;
        $this->assertNotNull($info);
        $this->assertEquals('Running', $info->worker_service);
        $this->assertEquals('Running', $info->shipper_service);
        $this->assertEquals('Running', $info->daemon_service);
        $this->assertEquals('50.763%', $info->disk_usage);
        $this->assertEquals('0.08203125, 0.0166015625, 0.00537109375', $info->load);
    }

    public function testSysinfoWithUnknownServices() {
        $this->assertFileExists($this->docker_job_log_file);
        $this->assertFileExists($this->sysinfo_log_file);

        FileUtils::writeFile(FileUtils::joinPaths($this->tmp_dir, "logs", "docker", "20251234.txt"), file_get_contents($this->docker_job_log_file));
        FileUtils::writeFile(FileUtils::joinPaths($this->tmp_dir, "logs", "sysinfo", "20251234.txt"), file_get_contents($this->sysinfo_log_file));
        $test = <<<SYSINFO
        ------------------------------
        System Info :: primary
        Worker Service: Running
        Shipper Service: Unknown Error
        Daemon Job Handler: Service Not Found
        Disk Usage: 50.55%
        System Load: (0.025, 0.01, 0.015)

        [Last ran on: 2025-01-13T21:00:01.328600]

        SYSINFO;
        file_put_contents(
            FileUtils::joinPaths($this->tmp_dir, "logs", "sysinfo", "20251234.txt"),
            $test,
            FILE_APPEND | LOCK_EX
        );

        $docker_ui = new DockerUI($this->core, [
            "autograding_containers" => json_decode($this::getAutogradingContainersJson(), true),
            "autograding_workers" => json_decode($this::getAutogradingWorkersJson(), true),
            "image_owners" => [],
        ]);

        $worker = $docker_ui->getWorkerMachines()[0];

        $info = $worker->system_information;
        $this->assertNotNull($info);
        $this->assertEquals('Running', $info->worker_service);
        $this->assertEquals('Unknown Error', $info->shipper_service);
        $this->assertNull($info->daemon_service);
        $this->assertEquals('50.55%', $info->disk_usage);
        $this->assertEquals('0.025, 0.01, 0.015', $info->load);

        $test = <<<SYSINFO
        ------------------------------
        System Info :: primary
        Worker Service: Service Not Found
        Shipper Service: Service Not Found
        Daemon Job Handler: Service Not Found
        Disk Usage: 50.56%
        System Load: (0.0278, 0.03, 0.025)

        [Last ran on: 2025-01-13T21:00:01.338600]

        SYSINFO;

        file_put_contents(
            FileUtils::joinPaths($this->tmp_dir, "logs", "sysinfo", "20251234.txt"),
            $test,
            FILE_APPEND | LOCK_EX
        );

        $docker_ui = new DockerUI($this->core, [
            "autograding_containers" => json_decode($this::getAutogradingContainersJson(), true),
            "autograding_workers" => json_decode($this::getAutogradingWorkersJson(), true),
            "image_owners" => [],
        ]);
        
        $worker = $docker_ui->getWorkerMachines()[0];
        $info = $worker->system_information;
        $this->assertNull($info->worker_service);
        $this->assertNull($info->shipper_service);
        $this->assertNull($info->daemon_service);
        $this->assertEquals('50.56%', $info->disk_usage);
        $this->assertEquals('0.0278, 0.03, 0.025', $info->load);
    }
}
