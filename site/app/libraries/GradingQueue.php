<?php

namespace app\libraries;

use app\models\gradeable\AutoGradedVersion;

/**
 * Class GradingQueue
 * @package app\libraries
 *
 * A class to represent the status of the grading queue
 */
class GradingQueue {

    /** @var string The prefix for each normal queue file */
    private $queue_file_prefix = '';
    /** @var string The path to the queue directory */
    private $queue_path = '';
    /** @var string[] An array of queue file names relative to $queue_path */
    private $queue_files = null;
    /** @var string[] An array of grading file names relative to $queue_path
     *      Note: These names still have the GRADING_FILE_PREFIX attached to them
     */
    private $grading_files = [];

    const GRADING_FILE_PREFIX = 'GRADING_';
    const QUEUE_FILE_SEPARATOR = '__';

    const NOT_QUEUED = -1;
    const GRADING = 0;

    public function __construct($semester, $course, $submitty_path) {
        $this->queue_path = FileUtils::joinPaths($submitty_path, 'to_be_graded_queue');
        $this->queue_file_prefix = implode(self::QUEUE_FILE_SEPARATOR, [$semester, $course]);
    }

    /**
     * Forces the queue state to be reloaded from the files on the disk
     */
    public function reloadQueue() {
        // Get all items in queue dir
        $all_files = scandir($this->queue_path);

        $grading_files = [];
        $queue_files = [];
        $times = [];

        // Filter the results so we only get files
        foreach ($all_files as $file) {
            $fqp = FileUtils::joinPaths($this->queue_path, $file);
            if (is_file($fqp)) {
                if (strpos($file, self::GRADING_FILE_PREFIX) !== false) {
                    $grading_files[] = $file;
                } else {
                    $queue_files[] = $file;

                    // Also, record the last modified of each item
                    $times[] = filemtime($fqp);
                }
            }
        }

        // Sort files by last modified time (descending)
        array_multisort($times, SORT_DESC, $queue_files);

        // Finally, set the member variables
        $this->queue_files = $queue_files;
        $this->grading_files = $grading_files;
    }

    /**
     * Loads the queue if it hasn't already been loaded yet
     */
    private function ensureLoadedQueue() {
        if($this->queue_files === null) {
            $this->reloadQueue();
        }
    }

    /**
     * Gets the position of the provided AutoGradedVersion from the queue
     * @param AutoGradedVersion $auto_graded_version
     * @return int The version's queue position, or GRADING if being graded, or NOT_QUEUED of not found
     */
    public function getQueueStatus(AutoGradedVersion $auto_graded_version) {
        $this->ensureLoadedQueue();

        // Generate the queue file names
        $queue_file = implode(self::QUEUE_FILE_SEPARATOR, [
            $this->queue_file_prefix,
            $auto_graded_version->getGradedGradeable()->getGradeable()->getId(),
            $auto_graded_version->getGradedGradeable()->getSubmitter()->getId(),
            $auto_graded_version->getVersion()
        ]);
        $grading_queue_file = self::GRADING_FILE_PREFIX . $queue_file;

        //FIXME: it would be nice to show the student which queue their assignment is in
        //FIXME:    but this could be a pretty expensive operation

        // First, check if its being graded
        $grading_status = array_search($grading_queue_file, $this->grading_files, true);
        if($grading_status !== false) {
            return self::GRADING;
        }

        // Then, check its position in the queue
        $queue_status = array_search($queue_file, $this->queue_files, true);
        if($queue_status === false) {
            // This means the file didn't exist when we loaded the queue state
            return self::NOT_QUEUED;
        } else {
            // Convert from 0-indexed array since 0 is self::GRADING
            return $queue_status + 1;
        }
    }

    /**
     * Gets the number of items in the queue
     * @return int
     */
    public function getQueueCount() {
        $this->ensureLoadedQueue();
        return count($this->queue_files);
    }

    /**
     * Gets the number of items being graded
     * @return int
     */
    public function getGradingCount() {
        $this->ensureLoadedQueue();
        return count($this->grading_files);
    }
}