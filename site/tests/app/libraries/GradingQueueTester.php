<?php

/** @noinspection PhpParamsInspection */

namespace tests\app\libraries;

use app\libraries\FileUtils;
use app\libraries\Utils;
use app\libraries\GradingQueue;
use app\models\gradeable\AutoGradedVersion;
use app\models\gradeable\Gradeable;
use app\models\gradeable\GradedGradeable;
use app\models\User;
use tests\BaseUnitTest;

class GradingQueueTester extends BaseUnitTest {
    private $path;
    private $queue_path;
    private $grading_path;
    private $version = 0;
    private $time = 0;

    // The three constants below control which queue a simulated queue file
    // will be placed in when `createAutogradedVersion()` is called.

    /**
     * No queue file should be created for the assignment.
     */
    private const NO_QUEUE = -1;
    /**
     * The queue file should be placed in the `to_be_graded_queue` directory.
     */
    private const DEFAULT_QUEUE = 0;
    /**
     * The queue file should be placed in a subdirectory of the `grading`
     * directory, mirroring the situation where a queue file has been assigned
     * to a worker but the worker has not yet picked up the file. Which worker
     * directory the file should be placed in is determined by the second
     * parameter of the `createAutogradedVersion()` function.
     */
    private const WORKER_QUEUE = 1;

    public function setUp(): void {
        $this->path = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
        FileUtils::createDir($this->path);
        $this->queue_path = FileUtils::joinPaths($this->path, 'to_be_graded_queue');
        FileUtils::createDir($this->queue_path);
        $this->grading_path = FileUtils::joinPaths($this->path, 'grading');
        FileUtils::createDir($this->grading_path);
    }

    public function tearDown(): void {
        if (file_exists($this->path)) {
            FileUtils::recursiveRmdir($this->path);
        }
    }

    private function createAutogradedVersion($queue_mode = GradingQueueTester::DEFAULT_QUEUE, $which_worker = null, $create_grading_file = false) {
        $autograded_version = $this->createMockModel(AutoGradedVersion::class);
        $graded_gradeable = $this->createMockModel(GradedGradeable::class);
        $gradeable = $this->createMockModel(Gradeable::class);
        $gradeable->method('getId')->willReturn('test');
        $submitter = $this->createMockModel(User::class);
        $submitter->method('getId')->willReturn('user');
        $graded_gradeable->method('getGradeable')->willReturn($gradeable);
        $graded_gradeable->method('getSubmitter')->willReturn($submitter);
        $autograded_version->method('getGradedGradeable')->willReturn($graded_gradeable);
        $autograded_version->method('getVersion')->willReturn($this->version);
        $filename = implode(GradingQueue::QUEUE_FILE_SEPARATOR, [
            's18',
            'csci1100',
            'test',
            'user',
            $this->version
        ]);
        if ($queue_mode === GradingQueueTester::DEFAULT_QUEUE) {
            touch(FileUtils::joinPaths($this->queue_path, $filename), $this->time);
        }
        elseif ($queue_mode === GradingQueueTester::WORKER_QUEUE) {
            $worker_dir = FileUtils::joinPaths($this->grading_path, $which_worker);
            FileUtils::createDir($worker_dir);

            touch(FileUtils::joinPaths($worker_dir, $filename), $this->time);
            if ($create_grading_file) {
                touch(
                    FileUtils::joinPaths($worker_dir, GradingQueue::GRADING_FILE_PREFIX . $filename),
                    $this->time
                );
            }
        }
        $this->version++;
        $this->time += 10;
        return $autograded_version;
    }

    public function testEmptyQueue() {
        $queue = new GradingQueue('s18', 'csci1100', $this->path);
        $this->assertEquals(0, $queue->getQueueCount());
        $this->assertEquals(0, $queue->getGradingCount());

        $this->assertEquals(
            GradingQueue::NOT_QUEUED,
            $queue->getQueueStatusAGV($this->createAutogradedVersion(GradingQueueTester::NO_QUEUE))
        );
    }

    public function testFileInQueue() {
        $queue = new GradingQueue('s18', 'csci1100', $this->path);
        $autograded_version = $this->createAutogradedVersion();
        $this->assertEquals(1, $queue->getQueueCount());
        $this->assertEquals(0, $queue->getGradingCount());

        $this->assertEquals(
            1,
            $queue->getQueueStatusAGV($autograded_version)
        );
    }

    public function testFileGrading() {
        $queue = new GradingQueue('s18', 'csci1100', $this->path);
        $autograded_version = $this->createAutogradedVersion(GradingQueueTester::WORKER_QUEUE, 'worker1', true);
        $this->assertEquals(1, $queue->getQueueCount());
        $this->assertEquals(1, $queue->getGradingCount());

        $this->assertEquals(
            GradingQueue::GRADING,
            $queue->getQueueStatusAGV($autograded_version)
        );
    }

    public function testTwoQueuedFilesOneGrading() {
        $queue = new GradingQueue('s18', 'csci1100', $this->path);
        $autograded_version1 = $this->createAutogradedVersion(GradingQueueTester::WORKER_QUEUE, 'worker1', true);
        $autograded_version2 = $this->createAutogradedVersion(GradingQueueTester::DEFAULT_QUEUE);
        $autograded_version3 = $this->createAutogradedVersion(GradingQueueTester::DEFAULT_QUEUE);
        $this->assertEquals(3, $queue->getQueueCount());
        $this->assertEquals(1, $queue->getGradingCount());

        $this->assertEquals(
            GradingQueue::GRADING,
            $queue->getQueueStatusAGV($autograded_version1)
        );

        $this->assertEquals(
            1,
            $queue->getQueueStatusAGV($autograded_version2)
        );
        $this->assertEquals(
            2,
            $queue->getQueueStatusAGV($autograded_version3)
        );
    }

    public function testReloadQueue() {
        $queue = new GradingQueue('s18', 'csci1100', $this->path);
        $autograded_version1 = $this->createAutogradedVersion();
        $this->assertEquals(1, $queue->getQueueCount());
        $this->assertEquals(1, $queue->getQueueStatusAGV($autograded_version1));
        $this->assertEquals(0, $queue->getGradingCount());
        $autograded_version2 = $this->createAutogradedVersion();
        $this->assertEquals(1, $queue->getQueueCount());
        $this->assertEquals(1, $queue->getQueueStatusAGV($autograded_version1));
        $queue->reloadQueue();
        $this->assertEquals(2, $queue->getQueueCount());
        $this->assertEquals(1, $queue->getQueueStatusAGV($autograded_version1));
        $this->assertEquals(2, $queue->getQueueStatusAGV($autograded_version2));
    }

    public function testMultipleGrading() {
        $queue = new GradingQueue('s18', 'csci1100', $this->path);
        $autograded_versions = [];
        for ($i = 0; $i < 3; $i++) {
            $autograded_versions[] = $this->createAutogradedVersion(GradingQueueTester::WORKER_QUEUE, 'worker' . ($i + 1), true);
        }
        foreach ($autograded_versions as $version) {
            $this->assertEquals(GradingQueue::GRADING, $queue->getQueueStatusAGV($version));
        }
    }

    public function testWorkerQueues() {
        $queue = new GradingQueue('s18', 'csci1100', $this->path);
        $worker1_agvs = [];
        $worker2_agvs = [];
        for ($i = 0; $i < 3; $i++) {
            $worker1_agvs[] = $this->createAutogradedVersion(GradingQueueTester::WORKER_QUEUE, 'worker1');
            $worker2_agvs[] = $this->createAutogradedVersion(GradingQueueTester::WORKER_QUEUE, 'worker2');
        }
        $this->assertEquals(6, $queue->getQueueCount());
        foreach ($worker1_agvs as $i => $autograded_version) {
            $this->assertEquals($i + 1, $queue->getQueueStatusAGV($autograded_version));
        }
        foreach ($worker2_agvs as $i => $autograded_version) {
            $this->assertEquals($i + 1, $queue->getQueueStatusAGV($autograded_version));
        }
    }
}
