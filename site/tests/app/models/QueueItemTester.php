<?php

declare(strict_types=1);

namespace tests\app\models;

use app\libraries\FileUtils;
use app\libraries\Utils;
use app\models\QueueItem;
use tests\BaseUnitTest;

class QueueItemTester extends BaseUnitTest {
    private $tmp_dir;

    public function setUp(): void {
        $this->tmp_dir = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
        FileUtils::createDir($this->tmp_dir);
    }

    public function tearDown(): void {
        FileUtils::recursiveRmdir($this->tmp_dir);
    }

    private function writeQueueFile(string $name, array $contents): string {
        $path = FileUtils::joinPaths($this->tmp_dir, $name);
        file_put_contents($path, json_encode($contents));
        return $path;
    }

    public function testNonGradingQueueItem(): void {
        $path = $this->writeQueueFile('queue_file.json', ['gradeable' => 'hw1']);

        $item = new QueueItem($path, filectime($path) + 100, false);

        $this->assertEquals(['gradeable' => 'hw1'], $item->getQueueObj());
        $this->assertEquals([], $item->getGradingQueueObj());
        $this->assertEquals(100, $item->getElapsedTime());
        $this->assertFalse($item->isRegrade());
    }

    public function testRegradeDetection(): void {
        $path = $this->writeQueueFile('queue_file.json', ['gradeable' => 'hw1', 'regrade' => true]);

        $item = new QueueItem($path, filectime($path), false);

        $this->assertTrue($item->isRegrade());
    }

    public function testGradingQueueItemReadsGradingFile(): void {
        $path = $this->writeQueueFile('queue_file.json', ['gradeable' => 'hw1']);
        $this->writeQueueFile('GRADING_queue_file.json', ['status' => 'grading']);

        $item = new QueueItem($path, filectime($path), true);

        $this->assertEquals(['status' => 'grading'], $item->getGradingQueueObj());
        $this->assertEquals(['gradeable' => 'hw1'], $item->getQueueObj());
    }

    public function testGradingQueueItemMissingGradingFile(): void {
        $path = $this->writeQueueFile('queue_file.json', ['gradeable' => 'hw1']);

        $item = new QueueItem($path, filectime($path), true);

        $this->assertEquals([], $item->getGradingQueueObj());
    }

    public function testUnparseableQueueFileLeavesEmptyQueueObj(): void {
        $path = FileUtils::joinPaths($this->tmp_dir, 'queue_file.json');
        file_put_contents($path, 'not valid json');

        $item = new QueueItem($path, filectime($path), false);

        $this->assertEquals([], $item->getQueueObj());
        $this->assertFalse($item->isRegrade());
    }
}
