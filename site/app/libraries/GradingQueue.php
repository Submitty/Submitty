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
    /** @var string[][] An array of queue file names relative to some worker's
     *      subdirectory under $grading_path. The first layer is indexed by
     *      worker, the second layer is a simple list.
     */
    private $subqueue_files = null;
    /** @var string[] An array of grading file names relative to $queue_path
     *      Note: These names still have the GRADING_FILE_PREFIX attached to them
     */
    private $grading_files = [];
    private $grading_path = '';
    private $grading_remaining = [];

    const GRADING_FILE_PREFIX = 'GRADING_';
    const VCS_FILE_PREFIX = 'VCS__';
    const QUEUE_FILE_SEPARATOR = '__';

    const NOT_QUEUED = -1;
    const GRADING = 0;

    public function __construct($semester, $course, $submitty_path) {
        $this->queue_path = FileUtils::joinPaths($submitty_path, 'to_be_graded_queue');
        $this->grading_path = FileUtils::joinPaths($submitty_path, 'grading');
        $this->queue_file_prefix = implode(self::QUEUE_FILE_SEPARATOR, [$semester, $course]);
    }

    /**
     * Forces the queue state to be reloaded from the files on the disk
     */
    public function reloadQueue() {
        // Get all items in queue dir
        $queued_files = scandir($this->queue_path);
        $grading_dirs = scandir($this->grading_path);

        $grading_files = [];
        $queue_files = [];
        $subqueue_files = [];
        $times = [];
        $subtimes = [];

        // Filter the results so we only get files
        foreach ($queued_files as $file) {
            $fqp = FileUtils::joinPaths($this->queue_path, $file);
            if (is_file($fqp)) {
                $queue_files[] = $file;

                // Also, record the last modified of each item
                $times[] = filemtime($fqp);
            }
        }

        foreach ($grading_dirs as $remote_dir) {
            $path = FileUtils::joinPaths($this->grading_path, $remote_dir);
            // First, we filter to directories that are neither `.` nor `..`.
            // These remote directories each correspond to an individual worker.
            if ($remote_dir !== "." && $remote_dir !== ".." && is_dir($path)) {
                $this_remote_files = scandir($path);
                foreach ($this_remote_files as $file) {
                    $full_path = FileUtils::joinPaths($path, $file);
                    // Keep only files in this directory. Additionally, if the
                    // filename is *NOT* prefixed by the prefix set in
                    // GRADING_FILE_PREFIX, then we still count this file as
                    // "in the queue" as the worker has yet to pick it up.
                    if (is_file($full_path)) {
                        if (strpos($file, self::GRADING_FILE_PREFIX) !== false) {
                            $grading_files[] = $file;
                        }
                        else {
                            if (!array_key_exists($remote_dir, $subqueue_files)) {
                                $subqueue_files[$remote_dir] = [];
                                $subtimes[$remote_dir] = [];
                            }
                            $subqueue_files[$remote_dir][] = $file;
                            $subtimes[$remote_dir][] = filemtime($full_path);
                        }
                    }
                }
            }
        }

        // Sort files by last modified time (descending)
        array_multisort($times, SORT_ASC, $queue_files);
        foreach ($subqueue_files as $worker => $subqueue) {
            array_multisort($subtimes[$worker], SORT_ASC, $subqueue);
        }

        // Finally, set the member variables
        $this->queue_files = $queue_files;
        $this->grading_files = $grading_files;
        $this->subqueue_files = $subqueue_files;
    }

    /**
     * Loads the queue if it hasn't already been loaded yet
     */
    private function ensureLoadedQueue() {
        if ($this->queue_files === null) {
            $this->reloadQueue();
        }
    }

    /**
     * Gets the position of the provided AutoGradedVersion from the queue
     * @param AutoGradedVersion $auto_graded_version
     * @return int The version's queue position, or GRADING if being graded, or NOT_QUEUED of not found
     */
    public function getQueueStatusAGV(AutoGradedVersion $auto_graded_version) {
        return $this->getQueueStatus(
            $auto_graded_version->getGradedGradeable()->getGradeable()->getId(),
            $auto_graded_version->getGradedGradeable()->getSubmitter()->getId(),
            $auto_graded_version->getVersion()
        );
    }

    /**
     * Gets the position of the provided autograding Gradeable from the queue
     * @param string $gradeableId
     * @param string $submitterId
     * @param int $version
     * @return int The version's queue position, or GRADING if being graded, or NOT_QUEUED of not found
     */
    public function getQueueStatus($gradeableId, $submitterId, $version) {
        $this->ensureLoadedQueue();

        // Generate the queue file names
        $queue_file = implode(self::QUEUE_FILE_SEPARATOR, [
            $this->queue_file_prefix,
            $gradeableId,
            $submitterId,
            $version
        ]);
        $grading_queue_file = self::GRADING_FILE_PREFIX . $queue_file;
        $vcs_queue_file = self::VCS_FILE_PREFIX . $queue_file;

        //FIXME: it would be nice to show the student which queue their assignment is in
        //FIXME:    but this could be a pretty expensive operation

        // First, check if its being graded
        $grading_status = array_search($grading_queue_file, $this->grading_files, true);
        if ($grading_status !== false) {
            return self::GRADING;
        }

        // Then, check its position in the queue
        $queue_status = array_search($queue_file, $this->queue_files, true);
        if ($queue_status === false) {
            // Also check for the vcs queue file, which will soon be converted into a regular queue file
            $queue_status = array_search($vcs_queue_file, $this->queue_files, true);
        }
        if ($queue_status === false) {
            // If it's not in the main queue, it's probably in some worker's
            // subqueue awaiting to be picked up. We search each worker's
            // subqueue and, if we find the queue file there, we stop
            // searching as we've found it.
            foreach ($this->subqueue_files as $subqueue) {
                $queue_status = array_search($queue_file, $subqueue, true);
                if ($queue_status !== false) {
                    break;
                }
            }
        }

        if ($queue_status !== false) {
            // Convert from 0-indexed array since 0 is self::GRADING
            return $queue_status + 1;
        }

        // Otherwise...  the file didn't exist when we loaded the queue state (likely something went wrong)
        return self::NOT_QUEUED;
    }

    /**
     * Gets the number of items in the queue
     * @return int
     */
    public function getQueueCount() {
        $this->ensureLoadedQueue();
        $count = count($this->queue_files);
        foreach ($this->subqueue_files as $subqueue) {
            $count += count($subqueue);
        }
        return $count;
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
