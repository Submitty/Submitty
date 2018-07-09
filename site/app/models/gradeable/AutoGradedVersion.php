<?php

namespace app\models\gradeable;


use app\exceptions\FileNotFoundException;
use app\libraries\Core;
use app\libraries\DateUtils;
use app\libraries\FileUtils;
use app\models\AbstractModel;

/**
 * Class AutoGradedVersion
 * @package app\models\gradeable
 *
 * Data about and results of autograding for one submission version
 *
 * @method int getVersion()
 * @method float getNonHiddenNonExtraCredit()
 * @method float getNonHiddenExtraCredit()
 * @method float getHiddenNonExtraCredit()
 * @method float getHiddenExtraCredit()
 * @method \DateTime getSubmissionTime()
 * @method bool isAutogradingComplete()
 */
class AutoGradedVersion extends AbstractModel {
    /** @var GradedGradeable Reference to the GradedGradeable */
    private $graded_gradeable = null;
    /** @property @var int The submission version for this AutoGradedVersion */
    protected $version = 0;
    /** @property @var float The number of "normal" points */
    protected $non_hidden_non_extra_credit = 0;
    /** @property @var float The number of "normal extra credit" points */
    protected $non_hidden_extra_credit = 0;
    /** @property @var float The number of "hidden" points */
    protected $hidden_non_extra_credit = 0;
    /** @property @var float The number of "hidden extra credit" points */
    protected $hidden_extra_credit = 0;
    /** @property @var \Datetime Time the user submitted this version */
    protected $submission_time = null;
    /** @property @var bool If the autograding has complete for this version */
    protected $autograding_complete = false;

    /** @property @var AutoGradedTestcase[] The testcases for this version indexed by testcase id (lazy loaded)  */
    private $graded_testcases = null;
    /** @property @var float The number of early submission incentive points this version is worth */
    private $early_incentive_points = 0.0;

    /** @property @var string[] An array of the names of all meta files in submission directory */
    private $meta_files = null;
    /** @property @var array[] An array indexed by part number of array of file paths
     *      Note: paths are relative to part directory
     *      Note: 0'th part contains all files, flattened
     */
    private $files = null;

    /** @property @var int The position of the submission in the queue (0 if being graded, -1 if not in queue)
     *      Note: null default value used to indicate that no queue status data has been loaded
     */
    private $queue_position = null;
    /** @property @var int The total length of the grading queue */
    private $queue_count = 0;
    /** @property @var int The total number of items being graded */
    private $queue_grading_count = 0;

    /**
     * AutoGradedVersion constructor.
     * @param Core $core
     * @param GradedGradeable $graded_gradeable
     * @param array $details
     * @throws \InvalidArgumentException If the submission time failed to parse
     */
    public function __construct(Core $core, GradedGradeable $graded_gradeable, array $details) {
        parent::__construct($core);

        if ($graded_gradeable === null) {
            throw new \InvalidArgumentException('Graded gradeable cannot be null');
        }
        $this->graded_gradeable = $graded_gradeable;
        $this->setVersionInternal($details['version']);
        $this->setPointsInternal($details);
        $this->setSubmissionTimeInternal($details['submission_time']);
        $this->setAutogradingCompleteInternal($details['autograding_complete']);
    }

    public function toArray() {
        $details = parent::toArray();

        // Make sure to convert the date into a string
        $details['submission_time'] = DateUtils::dateTimeToString($this->submission_time);

        // Serialize the lazy-loaded testcase results
        $details['testcases'] = parent::parseObject($this->getTestcases());

        return $details;
    }

    /**
     * Loads information about the status of out item in the queue, and the queue itself
     * TODO: the queue state should be loaded globally, and accessed by each instance
     *  independendently
     */
    private function loadQueueStatus() {
        $queue_path = FileUtils::joinPaths($this->core->getConfig()->getSubmittyPath(), 'to_be_graded_queue');

        $submitter_id = $this->graded_gradeable->getSubmitter()->getId();

        $queue_file = implode("__", array($this->core->getConfig()->getSemester(),
            $this->core->getConfig()->getCourse(), $this->graded_gradeable->getGradeable()->getId(),
            $submitter_id, $this->version));
        $grading_queue_file = "GRADING_" . $queue_file;

        //FIXME: it would be nice to show the student which queue their assignment is in
        //FIXME:    but this could be a pretty expensive operation

        $queued = file_exists(FileUtils::joinPaths($queue_path, $queue_file));
        if (file_exists(FileUtils::joinPaths($queue_path, $grading_queue_file))) {
            $this->queue_position = 0;
        }

        // Get all items in queue dir
        if ($queued === true) {
            $all_files = scandir($queue_path);

            $this->queue_grading_count = 0;
            $queue_files = [];
            $times = [];

            // Filter the results so we only get files
            foreach ($all_files as $file) {
                $fqp = FileUtils::joinPaths($queue_path, $file);
                if (is_file($fqp)) {
                    if (strpos($file, "GRADING_") !== false) {
                        $this->queue_grading_count++;
                    } else {
                        $queue_files[] = $file;

                        // Also, record the last modified of each item
                        $times[] = filemtime($fqp);
                    }
                }
            }
            $this->queue_count = count($queue_files);

            // Sort files by last modified time (descending)
            array_multisort($times, SORT_DESC, $queue_files);

            // Get our position in the queue
            $result = array_search($queue_file, $queue_files, true);
            if($result === false) {
                // This means our file got deleted between checking if it existed and
                //  calling `scandir`.  Pretty unlikely... don't mislead the user, so say its not queued
                $this->queue_position = -1;
            } else {
                $this->queue_position = $result;
            }
        } else {
            // Not in queue
            $this->queue_position = -1;
        }
    }

    /**
     * Loads information about all files submitted for this version
     */
    private function loadSubmissionFiles() {
        $submitter_id = $this->graded_gradeable->getSubmitter()->getId();
        $gradeable = $this->graded_gradeable->getGradeable();
        $course_path = $this->core->getConfig()->getCoursePath();
        $config = $gradeable->getAutogradingConfig();

        // Get the path to load files from (based on submission type)
        $dir = $gradeable->isVcs() ? 'checkout' : 'submissions';
        $path = FileUtils::joinPaths($course_path, $dir, $gradeable->getId(), $submitter_id, $this->version);

        // Now load all files in the directory, flattening the results
        $submitted_files = FileUtils::getAllFiles($path, array(), true);
        foreach ($submitted_files as $file => $details) {
            if (substr(basename($file), 0, 1) === '.') {
                $this->meta_files[$file] = $details;
            } else {
                $this->files[0][$file] = $details;
            }
        }

        // A second time, look through the folder, but now split up based on part number
        foreach ($config->getPartNames() as $i => $name) {
            foreach ($submitted_files as $file => $details) {
                $dir_name = "part{$i}/";
                if (substr($file, 0, strlen($dir_name)) === "part{$i}/") {
                    $this->files[$i][substr($file, strlen($dir_name), null)] = $details;
                }
            }
        }
    }

    /**
     * Loads AutoGradedTestcase instances for all testcases in this Gradeable from the disk
     */
    private function loadTestcases() {
        $submitter_id = $this->graded_gradeable->getSubmitter()->getId();
        $gradeable = $this->graded_gradeable->getGradeable();
        $course_path = $this->core->getConfig()->getCoursePath();
        $config = $gradeable->getAutogradingConfig();

        $path = FileUtils::joinPaths($course_path, 'results', $gradeable->getId(), $submitter_id, $this->version);

        // Load files produced by autograding
        $result_files = FileUtils::getAllFiles($path, [], true);
        $result_file_info = [];
        foreach ($result_files as $file => $details) {
            $result_file_info[$file] = $details;
        }

        // Load file that contains numeric results
        $result_details = FileUtils::readJsonFile(FileUtils::joinPaths($path, 'results.json'));
        if ($result_details === false) {
            throw new FileNotFoundException('Could not find results file for autograding version');
        }

        // Load the historical results (for early submission incentive)
        $history = FileUtils::readJsonFile(FileUtils::joinPaths($path, 'history.json'));
        if ($history !== false) {
            $last_results_timestamp = $history[count($history) - 1];
        } else {
            $last_results_timestamp = [
                'submission_time' => 'UNKNOWN',
                'grade_time' => 'UNKNOWN',
                'wait_time' => 'UNKNOWN'
            ];
        }

        // Load the testcase results (and calculate early incentive points)
        $result_details = array_merge($result_details, $last_results_timestamp);
        $result_details['num_autogrades'] = count($history);
        foreach ($config->getTestcases() as $testcase) {
            if (!isset($result_details['testcases'][$testcase->getIndex()])) {
                // TODO: Autograding results file was incomplete.  This is a big problem, but how should
                // TODO:   we handle this error
            }
            $graded_testcase = new AutoGradedTestcase(
                $this->core, $testcase, $path, $result_details['testcases'][$testcase->getIndex()]);
            $this->graded_testcases[$testcase->getIndex()] = $graded_testcase;

            if (in_array($testcase, $config->getEarlySubmissionTestCases())) {
                $this->early_incentive_points += $graded_testcase->getPoints();
            }
        }
    }

    /**
     * Gets All of the graded testcases for this version
     * @return AutoGradedTestcase[]
     */
    public function getTestcases() {
        if ($this->graded_testcases === null) {
            $this->loadTestcases();
        }
        return $this->graded_testcases;
    }

    /**
     * Gets the number of points earned that count towards early submission incentives
     * @return float
     */
    public function getEarlyIncentivePoints() {
        if($this->graded_gradeable === null) {
            $this->loadTestcases();
        }
        return $this->early_incentive_points;
    }

    /**
     * Gets an array of file details (indexed by file name) for all submitted files
     * @return array
     */
    public function getFiles() {
        return $this->getPartFiles(0);
    }

    /**
     * Gets an array of file details (indexed by file name) for the given part
     * @param int $part The submission box the file was uploaded with (0 for all parts)
     * @return array
     */
    public function getPartFiles($part = 0) {
        if($this->files === null) {
            $this->loadSubmissionFiles();
        }
        return $this->files[$part];
    }

    /**
     * Gets an array of file details (indexed by file name) for all meta files uploaded (i.e. Mac '._' files)
     * @return array
     */
    public function getMetaFiles() {
        if($this->files === null) {
            $this->loadSubmissionFiles();
        }
        return $this->meta_files;
    }

    /**
     * Gets if this version is in the queue to be graded
     * @return bool
     */
    public function isQueued() {
        if ($this->queue_position === null) {
            $this->loadQueueStatus();
        }
        return $this->queue_position > 0;
    }

    /**
     * Gets if this version is being graded
     * @return bool
     */
    public function isGrading() {
        if ($this->queue_position === null) {
            $this->loadQueueStatus();
        }
        return $this->queue_position === 0;
    }

    /**
     * Gets the position of this version in the queue
     * @return int 0 if being graded, -1 if not in queue, otherwise the queue count
     */
    public function getQueuePosition() {
        if($this->queue_position === null) {
            $this->loadQueueStatus();
        }
        return $this->queue_position;
    }

    /**
     * Gets the number of items in the queue
     * @return int
     */
    public function getQueueCount() {
        if($this->queue_position === null) {
            $this->loadQueueStatus();
        }
        return $this->queue_count;
    }

    /**
     * Gets the number of items being graded
     * @return int
     */
    public function getQueueGradingCount() {
        if($this->queue_position === null) {
            $this->loadQueueStatus();
        }
        return $this->queue_grading_count;
    }

    /**
     * Gets the total number of non-hidden points the submitter earned for this version
     * @return int
     */
    public function getNonHiddenPoints() {
        return $this->non_hidden_non_extra_credit + $this->non_hidden_extra_credit;
    }

    /**
     * Gets the percent of the possible visible points the submitter earned
     * @param bool $clamp True to clamp the output to 1
     * @return float percentage (0 to 1), or NAN if no visible percent
     */
    public function getNonHiddenPercent($clamp = false) {
        $divisor = $this->graded_gradeable->getGradeable()->getAutogradingConfig()->getTotalNonHiddenNonExtraCredit();
        $dividend = $this->getNonHiddenNonExtraCredit() + $this->getNonHiddenExtraCredit();

        // Avoid divide-by-zero (== not a typo)
        if($divisor == 0) {
            return NAN;
        }
        $result = floatval($dividend) / $divisor;

        if ($clamp === true && $result > 1.0) {
            return 1.0;
        } else if ($result < 0) {
            return 0.0;
        }
        return $result;
    }

    /**
     * Gets the percent of all possible points the submitter earned
     * @param bool $clamp True to clamp the output to 1
     * @return float percentage (0 to 1), or NAN if no points possible
     */
    public function getTotalPercent($clamp = false) {
        $config = $this->graded_gradeable->getGradeable()->getAutogradingConfig();
        $divisor = $config->getTotalNonHiddenNonExtraCredit() + $config->getTotalHiddenNonExtraCredit();
        $dividend = $this->getNonHiddenNonExtraCredit() + $this->getNonHiddenExtraCredit() +
            $this->getHiddenNonExtraCredit() + $this->getHiddenExtraCredit();

        // avoid divide-by-zero (== not a typo)
        if($divisor == 0) {
            return NAN;
        }
        $result = floatval($dividend) / $divisor;

        if ($clamp === true && $result > 1.0) {
            return 1.0;
        } else if ($result < 0) {
            return 0.0;
        }
        return $result;
    }

    /**
     * Gets the graded gradeable this version data is associated with
     * @return GradedGradeable the graded gradeable this version data is associated with
     */
    public function getGradedGradeable() {
        return $this->graded_gradeable;
    }

    /* Overridden setters with validation */

    /**
     * Sets the version this graded version data is for
     * @param int|string $version
     */
    private function setVersionInternal($version) {
        if ((is_int($version) || ctype_digit($version)) && intval($version) >= 0) {
            $this->version = intval($version);
        } else {
            throw new \InvalidArgumentException('Version number must be a non-negative integer');
        }
    }

    const point_properties = [
        'non_hidden_non_extra_credit',
        'non_hidden_extra_credit',
        'hidden_non_extra_credit',
        'hidden_extra_credit'
    ];

    /**
     * Sets the point properties and asserts that they're numeric
     * @param array $points
     */
    private function setPointsInternal(array $points) {
        foreach (self::point_properties as $property) {
            if (is_numeric($points[$property])) {
                $this->$property = floatval($points[$property]);
            } else {
                throw new \InvalidArgumentException('Graded version point values must be numeric');
            }
        }
    }

    /**
     * Sets the date that the submitter submitted this version
     * @param string|\DateTime $submission_time The date or date string of when the submitter submitted this version
     * @throws \InvalidArgumentException if $submission_time is null or an invalid \DateTime string
     */
    private function setSubmissionTimeInternal($submission_time) {
        if ($submission_time !== null) {
            try {
                $this->submission_time = DateUtils::parseDateTime($submission_time, $this->core->getConfig()->getTimezone());
            } catch(\Exception $e) {
                throw new \InvalidArgumentException('Graded version submission time format invalid');
            }
        } else {
            throw new \InvalidArgumentException('Graded version submission time must not be null');
        }
    }

    /**
     * Sets whether or not autograding has been completed for this version
     * @param bool $complete Is autograding complete for this version
     */
    private function setAutogradingCompleteInternal($complete) {
        $this->autograding_complete = $complete === 'true' || $complete === true;
    }

    /* Intentionally Unimplemented accessor methods (all setters) */

    /** @internal */
    public function setVersion($version) {
        throw new \BadFunctionCallException('Cannot set version number of graded version');
    }

    /** @internal */
    public function setNonHiddenNonExtraCredit($points) {
        throw new \BadFunctionCallException('Cannot set point values of graded version');
    }

    /** @internal */
    public function setNonHiddenExtraCredit($points) {
        throw new \BadFunctionCallException('Cannot set point values of graded version');
    }

    /** @internal */
    public function setHiddenNonExtraCredit($points) {
        throw new \BadFunctionCallException('Cannot set point values of graded version');
    }

    /** @internal */
    public function setHiddenExtraCredit($points) {
        throw new \BadFunctionCallException('Cannot set point values of graded version');
    }

    /** @internal */
    public function setSubmissionTime($submission_time) {
        throw new \BadFunctionCallException('Cannot set submission time of graded version');
    }

    /** @internal */
    public function setAutogradingComplete($complete) {
        throw new \BadFunctionCallException('Cannot set completeness of graded version');
    }
}
