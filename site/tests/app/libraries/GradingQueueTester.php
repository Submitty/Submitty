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
    private $version = 0;
    private $time = 0;

    public function setUp() {
        $this->path = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
        FileUtils::createDir($this->path);
        $this->queue_path = FileUtils::joinPaths($this->path, 'to_be_graded_queue');
        FileUtils::createDir($this->queue_path);
    }

    public function tearDown() {
        if (file_exists($this->path)) {
            FileUtils::recursiveRmdir($this->path);
        }
    }

    private function createAutogradedVersion($create_queue_file = true, $create_grading_file = false) {
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
        if ($create_queue_file) {
            touch(FileUtils::joinPaths($this->queue_path, $filename), $this->time);
        }
        if ($create_grading_file) {
            touch(
                FileUtils::joinPaths($this->queue_path, GradingQueue::GRADING_FILE_PREFIX . $filename),
                $this->time
            );
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
            $queue->getQueueStatus($this->createAutogradedVersion(false))
        );
    }

    public function testFileInQueue() {
        $queue = new GradingQueue('s18', 'csci1100', $this->path);
        $autograded_version = $this->createAutogradedVersion();
        $this->assertEquals(1, $queue->getQueueCount());
        $this->assertEquals(0, $queue->getGradingCount());

        $this->assertEquals(
            1,
            $queue->getQueueStatus($autograded_version)
        );
    }

    public function testFileGrading() {
        $queue = new GradingQueue('s18', 'csci1100', $this->path);
        $autograded_version = $this->createAutogradedVersion(true, true);
        $this->assertEquals(1, $queue->getQueueCount());
        $this->assertEquals(1, $queue->getGradingCount());

        $this->assertEquals(
            GradingQueue::GRADING,
            $queue->getQueueStatus($autograded_version)
        );
    }

    public function testTwoQueuedFilesOneGrading() {
        $queue = new GradingQueue('s18', 'csci1100', $this->path);
        $autograded_version1 = $this->createAutogradedVersion(true, true);
        $autograded_version2 = $this->createAutogradedVersion(true);
        $autograded_version3 = $this->createAutogradedVersion(true);
        $this->assertEquals(3, $queue->getQueueCount());
        $this->assertEquals(1, $queue->getGradingCount());

        $this->assertEquals(
            GradingQueue::GRADING,
            $queue->getQueueStatus($autograded_version1)
        );

        $this->assertEquals(
            2,
            $queue->getQueueStatus($autograded_version2)
        );
        $this->assertEquals(
            3,
            $queue->getQueueStatus($autograded_version3)
        );
    }

    public function testReloadQueue() {
        $queue = new GradingQueue('s18', 'csci1100', $this->path);
        $autograded_version1 = $this->createAutogradedVersion();
        $this->assertEquals(1, $queue->getQueueCount());
        $this->assertEquals(1, $queue->getQueueStatus($autograded_version1));
        $this->assertEquals(0, $queue->getGradingCount());
        $autograded_version2 = $this->createAutogradedVersion();
        $this->assertEquals(1, $queue->getQueueCount());
        $this->assertEquals(1, $queue->getQueueStatus($autograded_version1));
        $queue->reloadQueue();
        $this->assertEquals(2, $queue->getQueueCount());
        $this->assertEquals(1, $queue->getQueueStatus($autograded_version1));
        $this->assertEquals(2, $queue->getQueueStatus($autograded_version2));
    }
}
