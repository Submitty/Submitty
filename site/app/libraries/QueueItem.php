<?php

namespace app\libraries;

/**
 * Class QueueItem
 * Helper class used to interpret the files read in the autograding json files
 */
class QueueItem {
    /** @prop-read @var array */
    private $grading_queue_obj = [];
    /** @prop-read @var int */
    private $start_time = 0;
    /** @prop-read @var int */
    private $elapsed_time = 0;
    /** @prop-read @var array */
    private $queue_obj = [];
    /** @prop-read @var bool */
    private $regrade = false;

    public function getGradingQueueObj(): array {
        return $this->grading_queue_obj;
    }

    public function getStartTime(): int {
        return $this->start_time;
    }

    public function getElapsedTime(): int {
        return $this->elapsed_time;
    }

    public function getQueueObj(): array {
        return $this->queue_obj;
    }

    public function isRegrade(): bool {
        return $this->regrade;
    }

    public function __construct(string $json_file, int $epoch_time, bool $is_grading) {
        if ($is_grading) {
            $base = basename($json_file);
            $dir = dirname($json_file);
            $tmp = FileUtils::readJsonFile($dir . "/GRADING_" . $base);
            if ($tmp != false) {
                $this->grading_queue_obj = $tmp;
            }
        }
        $this->start_time = filemtime($json_file);
        $this->elapsed_time = $epoch_time - $this->start_time;
        $this->queue_obj = FileUtils::readJsonFile($json_file);
        $this->regrade = $this->queue_obj["regrade"];
    }
}
