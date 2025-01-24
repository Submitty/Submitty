<?php

namespace app\models;

use app\libraries\FileUtils;

/**
 * Class QueueItem
 * Helper class used to interpret the files read in the autograding json files
 * @method array getGradingQueueObj()
 * @method int getStartTime()
 * @method int getElapsedTime()
 * @method array getQueueObj()
 * @method bool isRegrade()
 */
class QueueItem extends AbstractModel {
    /** @prop-read
     * @var array */
    protected $grading_queue_obj = [];
    /** @prop-read
     * @var int */
    protected $start_time = 0;
    /** @prop-read
     * @var int */
    protected $elapsed_time = 0;
    /** @prop-read
     * @var array */
    protected $queue_obj = [];
    /** @prop-read
     * @var bool */
    protected $regrade = false;

    public function __construct(string $json_file, int $epoch_time, bool $is_grading) {
        if ($is_grading) {
            $base = basename($json_file);
            $dir = dirname($json_file);
            $tmp = FileUtils::readJsonFile($dir . "/GRADING_" . $base);
            if ($tmp != false) {
                $this->grading_queue_obj = $tmp;
            }
        }
        $this->start_time = filectime($json_file);
        $this->elapsed_time = $epoch_time - $this->start_time;

        $json = FileUtils::readJsonFile($json_file);
        // Queue or grading file does not exist or is not parseable
        if ($json !== false) {
            $this->queue_obj = $json;
            $this->regrade = array_key_exists("regrade", $this->queue_obj);
        }
    }
}
