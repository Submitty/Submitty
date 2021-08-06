<?php

namespace app\libraries;

/**
 * @method array getGradingQueueObj()
 * @method int getStartTime()
 * @method int getElapsedTime()
 * @method array getQueueObj()
 * @method bool isRegrade()
 */
class QueueItem{
    /** @var array */
    private $grading_queue_obj = [];
    /** @var int */
    private $start_time = 0;
    /** @var int */
    private $elapsed_time = 0;
    /** @var array */
    private $queue_obj = [];
    /** @var bool */
    private $is_regrade = false;

    public function __construct(string $json_file, int $epoch_time, bool $is_grading){
        if ($is_grading) {
            $this->grading_queue_obj = FileUtils::readJsonFile($json_file);
        }
        $this->start_time = filemtime($json_file);
        $this->elapsed_time = $epoch_time - $this->start_time;
        $this->queue_obj = FileUtils::readJsonFile($json_file);
        $this->is_regrade = in_array("regrade", $this->queue_obj);
    }
}
