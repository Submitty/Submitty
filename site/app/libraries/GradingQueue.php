<?php

namespace app\libraries;

use app\models\gradeable\AutoGradedVersion;
use app\models\QueueItem;

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
        $this->grading_path = FileUtils::joinPaths($submitty_path, 'in_progress_grading');
        if ($semester != null || $course != null) {
            $this->queue_file_prefix = implode(self::QUEUE_FILE_SEPARATOR, [$semester, $course]);
        }
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

    /**
     * Gets information on autograding queue, containing
     *  - Submissions being graded
     *      - Elapsed time in the queue
     *      - The machine handling this grading job
     *      - The submission's creator which should be hidden unless the one accessing this info is an instructor of the course
     *  - Submissions in the queue
     *      - Categorized by interactive (non-regrade) and regrade jobs
     *      - The number of grading jobs requiring each capability
     *  - The submissions' gradeable id and affiliated course
     * @return array
     */
    public function getAutogradingInfo(string $config_path): array {
        // Get information on the worker machines
        $open_autograding_workers_json = FileUtils::readJsonFile(
            FileUtils::joinPaths($config_path, "autograding_workers.json")
        );

        $this->ensureLoadedQueue();

        $epoch_time = time();

        $machine_grading_counts = [];
        $capability_queue_counts = [];
        $workers = [];
        $ongoing_job_info = [];
        $course_info = [];

        foreach ($open_autograding_workers_json as $machine => $details) {
            $machine_grading_counts[$machine] = 0;
            foreach ($details["capabilities"] as $c) {
                $capability_queue_counts[$c] = 0;
            }
            $workers[$machine] = $details["num_autograding_workers"];
        }

        ksort($capability_queue_counts);

        $queue_counts = [];
        $queue_counts["interactive"] = 0;
        $queue_counts["interactive_ongoing"] = 0;
        $queue_counts["regrade"] = 0;
        $queue_counts["regrade_ongoing"] = 0;

        foreach ($this->queue_files as $full_path_file) {
            $path = FileUtils::joinPaths($this->queue_path, $full_path_file);
            $this->updateDetailedQueueCount(
                $path,
                false,
                $epoch_time,
                $queue_counts,
                $capability_queue_counts,
                $machine_grading_counts,
                $open_autograding_workers_json,
                $course_info
            );
        }

        $started = [];
        $not_yet_started = [];
        $all_files = $this->subqueue_files;

        foreach ($all_files as $worker => $files) {
            foreach ($files as $file) {
                $path = FileUtils::joinPaths($this->grading_path, $worker, self::GRADING_FILE_PREFIX . $file);
                if (file_exists($path)) {
                    $started[] = FileUtils::joinPaths($worker, $file);
                }
                else {
                    $not_yet_started[] = FileUtils::joinPaths($worker, $file);
                }
            }
        }

        foreach ($started as $file) {
            $path = FileUtils::joinPaths($this->grading_path, $file);
            $job_file = $this->updateDetailedQueueCount(
                $path,
                true,
                $epoch_time,
                $queue_counts,
                $capability_queue_counts,
                $machine_grading_counts,
                $open_autograding_workers_json,
                $course_info
            );
            $file_segments = explode("/", $file);
            $machine = substr($file_segments[0], 0, strrpos($file_segments[0], "_", -1));
            $file_segments = explode("__", $file_segments[1]);
            $elapsed_time = "";
            // Calculate the days elapsed
            if ($job_file["elapsed_time"] > 60 * 60 * 24) {
                $elapsed_time .= floor($job_file["elapsed_time"] / (60 * 60 * 24)) . " Days ";
                $job_file["elapsed_time"] = $job_file["elapsed_time"] % (60 * 60 * 24);
            }
            // Calculate the hours elapsed
            $elapsed_time .= str_pad(strval(floor($job_file["elapsed_time"] / (60 * 60))), 2, "0", STR_PAD_LEFT) . ":";
            $job_file["elapsed_time"] = $job_file["elapsed_time"] % (60 * 60);
            // Format the string with the minutes and seconds elapsed
            $elapsed_time .= str_pad(strval(floor($job_file["elapsed_time"] / 60)), 2, "0", STR_PAD_LEFT) . ":"
                . str_pad(strval($job_file["elapsed_time"] % 60), 2, "0", STR_PAD_LEFT);
            $ongoing_job_info[$machine][] = [
                "term" => $file_segments[0],
                "course" => $file_segments[1],
                "gradeable_id" => $file_segments[2],
                "user_id" => $file_segments[3] ?? "",
                "regrade" => $job_file["regrade"],
                "elapsed_time" => $elapsed_time,
                "error" => $job_file["error"],
                "stale" => $job_file["stale"]
            ];
        }

        // These are files in the grading directory but does not have a GRADING_ file, so grading has not started yet
        foreach ($not_yet_started as $file) {
            $path = FileUtils::joinPaths($this->grading_path, $file);
            $this->updateDetailedQueueCount(
                $path,
                false,
                $epoch_time,
                $queue_counts,
                $capability_queue_counts,
                $machine_grading_counts,
                $open_autograding_workers_json,
                $course_info
            );
        }

        return [
            "machine_grading_counts" => $machine_grading_counts,
            "capability_queue_counts" => $capability_queue_counts,
            "queue_counts" => $queue_counts,
            "num_autograding_workers" => $workers,
            "course_info" => $course_info,
            "ongoing_job_info" => $ongoing_job_info
        ];
    }

    // Helper function used to interpret the job files and update the count variables appropriately
    private function updateDetailedQueueCount(
        string $queue_or_grading_file,
        bool $is_grading,
        int $epoch_time,
        array &$detailed_queue_counts,
        array &$capability_queue_counts,
        array &$machine_grading_counts,
        array $open_autograding_workers_json,
        array &$course_info
    ): array {
        $stale = false;
        $error = "";
        $regrade = false;

        $elapsed_time = -1;

        $job_file = basename($queue_or_grading_file);

        try {
            $entry = new QueueItem($queue_or_grading_file, $epoch_time, $is_grading);
            $regrade = $entry->isRegrade();
            if ($regrade) {
                if ($is_grading) {
                    $detailed_queue_counts["regrade_ongoing"] += 1;
                }
                else {
                    $detailed_queue_counts["regrade"] += 1;
                }
            }
            else {
                if ($is_grading) {
                    $detailed_queue_counts["interactive_ongoing"] += 1;
                }
                else {
                    $detailed_queue_counts["interactive"] += 1;
                }
            }
            $capability = "default";
            if (array_key_exists("required_capabilities", $entry->getQueueObj())) {
                $capability = $entry->getQueueObj()["required_capabilities"];
            }

            if (!$is_grading) {
                if (!array_key_exists($capability, $capability_queue_counts)) {
                    $error .= "ERROR: {$job_file} requires {$capability} which is not provided by any worker";
                }
                else {
                    $capability_queue_counts[$capability] += 1;

                    $enabled_worker = false;
                    foreach ($open_autograding_workers_json as $machine) {
                        if ($machine["enabled"]) {
                            if (in_array($capability, $machine["capabilities"])) {
                                $enabled_worker = true;
                            }
                        }
                    }

                    if (!$enabled_worker) {
                        $error .= "ERROR: {$job_file} requires {$capability} which is not provided by any *ENABLED* worker";
                    }
                }
            }
            else {
                $max_time = $entry->getQueueObj()["max_possible_grading_time"];
                $full_machine = $entry->getGradingQueueObj()['machine'];

                $grading_machine = "NONE";
                foreach ($open_autograding_workers_json as $machine => $details) {
                    $m = $details["address"];
                    if ($details["username"] !== "") {
                        $m = "{$details['username']}@{$m}";
                    }
                    if ($full_machine === $m) {
                        $grading_machine = $machine;
                        break;
                    }
                }
                $machine_grading_counts[$grading_machine] += 1;

                $elapsed_time = $entry->getElapsedTime();
                if ($elapsed_time > $max_time) {
                    $stale = true;
                }
            }
            $file_segments = explode("__", $job_file);
            if (!array_key_exists($file_segments[0] . "__" . $file_segments[1], $course_info)) {
                $course_info[$file_segments[0] . "__" . $file_segments[1]] = [];
            }

            if (!array_key_exists($file_segments[2], $course_info[$file_segments[0] . "__" . $file_segments[1]])) {
                $course_info[$file_segments[0] . "__" . $file_segments[1]][$file_segments[2]] = ["regrade" => 0, "interactive" => 0];
            }

            if ($regrade) {
                $course_info[$file_segments[0] . "__" . $file_segments[1]][$file_segments[2]]["regrade"] += 1;
            }
            else {
                $course_info[$file_segments[0] . "__" . $file_segments[1]][$file_segments[2]]["interactive"] += 1;
            }
        }
        catch (\Exception $e) {
            $error .= "Could not read for {$queue_or_grading_file}: {$e->getMessage()}";
        }
        return [
            "elapsed_time" => $elapsed_time,
            "stale" => $stale,
            "error" => $error,
            "regrade" => $regrade
        ];
    }
}
