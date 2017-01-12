<?php

namespace app\models;

use app\exceptions\NotImplementedException;
use app\libraries\Core;
use app\libraries\FileUtils;
use app\libraries\GradeableType;
use app\libraries\Utils;

/**
 * Class Gradeable
 *
 * Model of the current gradeable being looked at for submission by the student. This information is a combination of
 * the info contained in the form json file or database, various result json files, and the version history file in the
 * submission folder. This abstract class is extended by GradeableFile (which loads the form json file) and
 * GradeableDb (which loads the data from the database). Additionally, it'll hold the relevant information necessary
 * for the TA to be able to grade this.
 */
abstract class Gradeable {

    /** @var Core */
    protected $core;
    
    /** @var string Id of the gradeable (must be unique) */
    protected $id;

    /** @var int  */
    protected $gd_id;
    
    /** @var string Name of the gradeable */
    protected $name;
    
    /** @var int GradeableType set for this Gradeable */
    protected $type;
    
    /** @var string Instructions to give to TA for grading */
    protected $ta_instructions = "";
    
    /** @var bool Is this a team gradeable */
    protected $team_gradeable = false;
    
    /** @var string Iris Bucket to place gradeable */
    protected $bucket = null;
    
    /** @var int Minimum group that's allowed to submit grades for this gradeable */
    protected $minimum_grading_group = 1;

    /** @var \DateTime|null Date for when grading can view */
    protected $ta_view_date = null;

    /** @var \DateTime|null Date for when grading can start */
    protected $grade_start_date = null;

    /** @var \DateTime|null Date for when the grade will be released to students */
    protected $grade_released_date = null;

    protected $ta_grades_released = false;

    /** @var bool Should the gradeable be graded by registration section (or by rotating section) */
    protected $grade_by_registration = true;
    
    protected $components = null;

    /* Config variables that are only for electronic submissions */
    protected $has_config = false;
    
    /** @var \DateTime|null When is an electronic submission open to students */
    protected $open_date = null;

    /** @var \DateTime|null Due date for an electronic submission */
    protected $due_date = null;

    /** @var bool Is the electronic submission a SVN repository or allow uploads */
    protected $is_repository = false;

    /** @var string What is the subdirectory for SVN */
    protected $subdirectory = "";

    /** @var int Number of days you can submit */
    protected $late_days = 0;

    /** @var string Url to any instructions for the gradeable for students */
    protected $instructions_url = "";

    /** @var string Path to the config.json file used to build the config/build/build_XXX.json file */
    protected $config_path = "";

    /** @var float Precision to allow for inputting points when grading (such that precision of 0.5 then allows grades
     * of 0, 0.5, 1, 1.5, etc.) */
    protected $point_precision = 0;

    /** @var bool Is there any TA grading to be done for this gradeable (ie. any rubric questions) */
    protected $ta_grading = false;
    protected $questions = array();

    /* Config variables that are only for checkpoints */
    protected $checkpoints = array();

    /* Config variables that are only for numeric/text types */
    protected $numerics = array();
    protected $texts = array();

    /* Config variables that are for both checkpoints and numeric/text types */
    protected $optional_ta_message = false;

    /* Config variables for submission details for this gradeable */
    /** @var int Max size (in bytes) allowed for the submission */
    protected $max_size = 50000;
    /** @var int Max number of submission allowed before a student starts suffering deductions every 10 submissions */
    /* NOTE:  This should never be used.  It should always be set in the gradeables build.json file. */
    protected $max_submissions = 20;

    /** @var float Non hidden, non extra credit points */
    protected $normal_points = 0;

    /**  @var float Non hidden points (including extra credit) */
    protected $non_hidden_points = 0;

    /** @var GradeableTestcase[] Autograding testcases for the gradeable */
    protected $testcases = array();

    /** @var string Message to show for the gradeable above all submission results */
    protected $message = "";

    /** @var int  */
    protected $num_parts = 1;

    /** @var string[] */
    protected $part_names = array();

    /* Variables for submission details (such as attempts used, etc.) */
    protected $submissions = 0;

    /**
     * @var int $active_version  The set active version for the assignment
     */
    protected $active_version = -1;
    /** @var int $current The current version of the assignment being viewed */
    protected $current = -1;
    /** @var int $highest Highest version submitted for an assignment */
    protected $highest = 0;

    protected $versions = array();

    /** @var array Array of all files for a specified submission number where each key is a previous file and then each element
     * is an array that contains filename, file path, and the file size. */
    protected $submitted_files = array();
    protected $svn_files = array();
    protected $meta_files = array();
    protected $previous_files = array();

    protected $result_details;

    protected $grade_file = null;

    protected $in_interactive_queue = false;
    protected $grading_interactive_queue = false;
    protected $in_batch_queue = false;
    protected $grading_batch_queue = false;

    protected $grader_id = null;
    protected $overall_comment = "";
    /** @var int code representing the state of electronic submission where 0 = not submitted, 1 = fine, 2 = late,
     * 3 = too late */
    protected $status;
    protected $graded_version = null;

    protected $interactive_queue_total = 0;
    protected $interactive_queue_position = 0;
    protected $batch_queue_total = 0;
    protected $batch_queue_position = 0;
    protected $grading_total = 0;

    protected $been_autograded = false;

    protected $total_auto_non_hidden_non_extra_credit = 0;
    protected $total_auto_non_hidden_extra_credit = 0;
    protected $total_auto_hidden_non_extra_credit = 0;
    protected $total_auto_hidden_extra_credit = 0;

    protected $graded_auto_non_hidden_non_extra_credit = 0;
    protected $graded_auto_non_hidden_extra_credit = 0;
    protected $graded_auto_hidden_non_extra_credit = 0;
    protected $graded_auto_hidden_extra_credit = 0;
    protected $submission_time = null;

    protected $been_tagraded = false;

    protected $graded_tagrading = 0;

    protected $total_tagrading_non_extra_credit = 0;
    protected $total_tagrading_extra_credit = 0;

    protected $user = null;

    public function __construct(Core $core, $id) {
        $this->core = $core;
        $this->id = $id;
    }

    /**
     * Loads the config/build/build_*.json file for a gradeable
     */
    protected function loadGradeableConfig() {
        if ($this->type !== GradeableType::ELECTRONIC_FILE) {
            return;
        }

        $details = GradeableAutogradingConfig::getConfig($this->core, $this->getId());

        // Was there actually a config file to read from
        if ($details === false) {
            return;
        }

        $this->has_config = true;

        if (isset($details['max_submission_size'])) {
            $this->max_size = floatval($details['max_submission_size']);
        }

        if (isset($details['max_submissions'])) {
            $this->max_submissions = intval($details['max_submissions']);
        }

        if (isset($details['assignment_message'])) {
            $this->message = Utils::prepareHtmlString($details['assignment_message']);
        }

        if (isset($details['num_parts'])) {
            $this->num_parts = intval($details['num_parts']);
            if ($this->num_parts < 1) {
                $this->num_parts = 1;
            }
        }

        for ($i = 1; $i <= $this->num_parts; $i++) {
            $this->previous_files[$i] = array();
            $j = $i - 1;
            if (isset($details['part_names']) && isset($details['part_names'][$j]) &&
                trim($details['part_names'][$j]) !== "") {
                $this->part_names[$i] = $details['part_names'][$j];
            }
            else {
                $this->part_names[$i] = "Part ".$i;
            }
        }

        if (isset($details['testcases'])) {
            foreach ($details['testcases'] as $idx => $testcase) {
                $testcase = new GradeableTestcase($this->core, $testcase, $idx);
                $this->testcases[] = $testcase;
                if ($testcase->getPoints() > 0) {
                    if ($testcase->isHidden() && $testcase->isExtraCredit()) {
                        $this->total_auto_hidden_extra_credit += $testcase->getPoints();
                    }
                    else if ($testcase->isHidden() && !$testcase->isExtraCredit()) {
                        $this->total_auto_hidden_non_extra_credit += $testcase->getPoints();
                    }
                    else if (!$testcase->isHidden() && $testcase->isExtraCredit()) {
                        $this->total_auto_non_hidden_extra_credit += $testcase->getPoints();
                    }
                    else {
                        $this->total_auto_non_hidden_non_extra_credit += $testcase->getPoints();
                    }
                }

                if ($testcase->getNonHiddenNonExtraCreditPoints() >= 0) {
                  $this->normal_points += $testcase->getNonHiddenNonExtraCreditPoints();
                }
                if ($testcase->getNonHiddenPoints() >= 0) {
                  $this->non_hidden_points += $testcase->getNonHiddenPoints();
                }
            }
        }
    }

    /**
     * Sets the grading queue status of the gradeable. We don't really care
     */
    public function setQueueStatus() {
        $interactive_queue = $this->core->getConfig()->getSubmittyPath()."/to_be_graded_interactive";
        $batch_queue = $this->core->getConfig()->getSubmittyPath()."/to_be_graded_batch";

        $queue_file = implode("__", array($this->core->getConfig()->getSemester(),
                                          $this->core->getConfig()->getCourse(), $this->id,
                                          $this->core->getUser()->getId(), $this->current));
        $grade_file = "GRADING_".$queue_file;

        $this->in_interactive_queue = file_exists($interactive_queue."/".$queue_file);
        $this->in_batch_queue = file_exists($batch_queue."/".$queue_file);
        $this->grading_interactive_queue = file_exists($interactive_queue."/".$grade_file);
        $this->grading_batch_queue = file_exists($batch_queue."/".$grade_file);

        $queue_count = 0;
        $grading_count = 0;
        if($this->in_interactive_queue === true) {
            $files = scandir($interactive_queue);
            $f = array();
            $times = array();
            foreach($files as $file) {
              if(is_file($interactive_queue.'/'.$file) && ($file !== "..") && ($file !== ".") && !in_array($file, $f)) {
                  $f[] = $file;
                  $times[] = filemtime($interactive_queue.'/'.$file);
              }
            }
            array_multisort($times,SORT_DESC,$f); //Sorted By Descending Here

            foreach($f as $file) {
                if(is_file($interactive_queue.'/'.$file) && ($file !== "..") && ($file !== ".")) {
                    if(strpos($file, "GRADING_") !== false) {
                        $grading_count = $grading_count + 1;
                    }
                    else {
                        $queue_count = $queue_count + 1;
                        if($file === $queue_file) {
                            $this->interactive_queue_position = $queue_count;
                        }
                    }
                }
            }

            /* Note:  Once permissions to access batch queue from interactive queue has been sorted, then can add in
                      the code below to count the full total of submissions being graded across both queues */
            /*$files = @scandir($batch_queue);
            // Count the number being graded in the batch queue to get total of submissions currently being graded
            foreach($files as $file) {
                if(strpos($file, "GRADING_") !== false) {
                    $grading_count = $grading_count + 1;
                }
            }*/

            $this->interactive_queue_total = $queue_count;
            $this->grading_total = $grading_count;
        }
        else if($this->in_batch_queue === true) {
            $files = scandir($batch_queue);
            $f = array();
            $times = array();
            foreach($files as $file){
              if(is_file($batch_queue.'/'.$file)){
                $f[] = $file;
                $times[] = filemtime($batch_queue.'/'.$file);
              }
            }
            array_multisort($times,SORT_DESC,$f); //Sort By Descending Here

            foreach($f as $file) {
                if(strpos($file, "GRADING_") !== false) {
                    $grading_count = $grading_count + 1;
                }
                else {
                    $queue_count = $queue_count + 1;
                    if($file === $queue_file) {
                        $this->batch_queue_position = $queue_count;
                    }
                }
            }

            /* Note:  Once permissions to access interactive queue from batch queue has been sorted, then can add in
                      the code below to count the full total of submissions being graded across both queues */
            /* $files = @scandir($interactive_queue);
            // Count the number being graded in the batch queue to get total of submissions currently being graded
            foreach($files as $file) {
                if(strpos($file, "GRADING_") !== false) {
                    $grading_count = $grading_count + 1;
                }
            }*/
            $this->batch_queue_total = $queue_count;
            $this->grading_total = $grading_count;
        }
        if($this->in_interactive_queue === false && $this->in_batch_queue === false) {
            $this->interactive_queue_position = 0;
            $this->interactive_queue_total = 0;
            $this->batch_queue_position = 0;
            $this->batch_queue_total = 0;
            $this->grading_total = 0;
        }
    }

    /**
     * Loads submission details about an electronic submission from the submissions/ and
     * results/ directories and their respective json files.
     */
    public function loadResultDetails() {
        if ($this->type !== GradeableType::ELECTRONIC_FILE) {
            return;
        }

        if (!$this->hasConfig()) {
            return;
        }

        $course_path = $this->core->getConfig()->getCoursePath();

        $submission_path = $course_path."/submissions/".$this->id."/".$this->core->getUser()->getId();
        $svn_path = $course_path."/checkout/".$this->id."/".$this->core->getUser()->getId();
        $results_path = $course_path."/results/".$this->id."/".$this->core->getUser()->getId();

        $this->components = $this->core->getQueries()->getGradeableComponents($this->id, $this->gd_id);
        $this->versions = $this->core->getQueries()->getGradeableVersions($this->id, $this->core->getUser()->getId(), $this->getDueDate());

        if (count($this->versions) > 0) {
            $this->highest = Utils::getLastArrayElement($this->versions)->getVersion();
        }

        $this->submissions = count($this->versions);

        if (isset($_REQUEST['gradeable_version'])) {
            $this->current = intval($_REQUEST['gradeable_version']);
        }

        if ($this->current < 0 && $this->active_version >= 0) {
            $this->current = $this->active_version;
        }
        else if ($this->current > $this->submissions) {
            $this->current = $this->active_version;
        }

        $this->setQueueStatus();

        $submission_current_path = $submission_path."/".$this->current;
        $submitted_files = FileUtils::getAllFiles($submission_current_path, array(), true);
        foreach ($submitted_files as $file => $details) {
            if (substr(basename($file), 0, 1) === '.') {
                $this->meta_files[$file] = $details;
            }
            else {
                $this->submitted_files[$file] = $details;
            }
        }

        $svn_current_path = $svn_path."/".$this->current;
        $svn_files = FileUtils::getAllFiles($svn_current_path, array(), true);
        foreach ($svn_files as $file => $details) {
            $this->svn_files[$file] = $details;
        }

        if ($this->getNumParts() > 1) {
            for ($i = 1; $i <= $this->getNumParts(); $i++) {
                $this->previous_files[$i] = array();
                foreach ($this->submitted_files as $file => $details) {
                    if (substr($file, 0, strlen("part{$i}/")) === "part{$i}/") {
                        $this->previous_files[$i][$file] = $details;
                    }
                }
            }
        }
        else {
            $this->previous_files[1] = $this->submitted_files;
        }

        if ($this->current > 0) {
            $this->result_details = FileUtils::readJsonFile(FileUtils::joinPaths($results_path, $this->current, "results.json"));
            if ($this->result_details !== false) {
                $results_history = FileUtils::readJsonFile(FileUtils::joinPaths($results_path, $this->current, "results_history.json"));
                if ($results_history !== false) {
                    $last_results_timestamp = $results_history[count($results_history) - 1];
                } else {
                    $last_results_timestamp = array('submission_time' => "UNKNOWN", "grade_time" => "UNKOWN",
                        "wait_time" => "UNKNOWN");
                }
                $this->result_details = array_merge($this->result_details, $last_results_timestamp);
                $this->result_details['num_autogrades'] = count($results_history);
                for ($i = 0; $i < count($this->result_details['testcases']); $i++) {
                    $this->testcases[$i]->addResultTestcase($this->result_details['testcases'][$i], FileUtils::joinPaths($results_path, $this->current));
                }
            }
        }

        $grade_file = $this->core->getConfig()->getCoursePath()."/reports/".$this->getId()."/".$this->core->getUser()->getId().".txt";
        if (is_file($grade_file)) {
            $this->grade_file = htmlentities(file_get_contents($grade_file));
        }
    }

    public function getId() {
        return $this->id;
    }

    public function getName() {
        return $this->name;
    }

    public function getType() {
        return $this->type;
    }

    public function getNumParts() {
        return $this->num_parts;
    }

    public function getPartsNames() {
        return $this->part_names;
    }

    public function getHighestVersion() {
        return $this->highest;
    }

    public function getActiveVersion() {
        return $this->active_version;
    }

    /**
     * @return GradeableVersion|null
     */
    public function getCurrentVersion() {
        if (!isset($this->versions[$this->current])) {
            return null;
        }
        return $this->versions[$this->current];
    }

    public function getPreviousFiles($part = 1) {
        $part = ($this->getNumParts() < $part || $part < 1) ? 1 : $part;
        return $this->previous_files[$part];
    }

    public function getMaxSubmissions() {
        return $this->max_submissions;
    }

    public function getMaxSize() {
        return $this->max_size;
    }

    public function getSubmissionCount() {
        return $this->submissions;
    }

    public function getAllowedLateDays() {
        return $this->late_days;
    }

    /**
     * @return GradeableVersion[]
     */
    public function getVersions() {
        return $this->versions;
    }

    /**
     * Returns the total number of points for testcases that are not hidden nor are extra credit
     * @return int
     */
    public function getNormalPoints() {
        return $this->normal_points;
    }

    public function getTotalNonHiddenNonExtraCreditPoints() {
        return $this->total_auto_non_hidden_non_extra_credit;
    }

    public function getGradedNonHiddenPoints() {
        return $this->graded_auto_non_hidden_extra_credit + $this->graded_auto_non_hidden_non_extra_credit;
    }

    public function getGradedAutograderPoints() {
        return $this->graded_auto_non_hidden_extra_credit +
            $this->graded_auto_non_hidden_non_extra_credit +
            $this->graded_auto_hidden_extra_credit +
            $this->graded_auto_hidden_non_extra_credit;
    }

    public function getTotalAutograderNonExtraCreditPoints() {
        return $this->total_auto_hidden_non_extra_credit + $this->total_auto_non_hidden_non_extra_credit;
    }

    public function getGradedTAPoints() {
        return $this->graded_tagrading;
    }

    public function getTotalTANonExtraCreditPoints() {
        return $this->total_tagrading_non_extra_credit;
    }

    public function getDueDate() {
        return $this->due_date;
    }

    public function getTAViewDate(){
        return $this->ta_view_date;
    }

    public function getGradeStartDate(){
        return $this->grade_start_date;
    }

    public function getGradeReleasedDate(){
        return $this->grade_released_date;
    }

    public function getOpenDate() {
        return $this->open_date;
    }

    public function getDaysLate() {
        return ($this->hasResults()) ? $this->getCurrentVersion()->getDaysLate() : 0;
    }

    public function getInstructionsURL(){
        return $this->instructions_url;
    }

    /**
     * Check to see if we have the result_details array from the results directory.
     * If false, we don't want to display any result details to the user about the
     * version.
     *
     * @return bool
     */
    public function hasResults() {
        return isset($this->result_details);
    }

    public function getResults() {
        return $this->result_details;
    }

    public function getSubmittedFiles() {
        return $this->submitted_files;
    }

    public function getSvnFiles() {
        return $this->svn_files;
    }

    public function getTestcases() {
        return $this->testcases;
    }

    public function hasAssignmentMessage() {
        return trim($this->message) !== "";
    }

    public function getAssignmentMessage() {
        return $this->message;
    }

    public function useSvnCheckout() {
        return $this->is_repository;
    }

    public function beenAutograded() {
        return $this->been_autograded;
    }

    public function beenTAgraded() {
        return $this->been_tagraded;
    }

    public function hasGradeFile() {
        return $this->grade_file !== null;
    }

    public function getGradeFile() {
        return $this->grade_file;
    }

    public function useTAGrading() {
        return $this->ta_grading;
    }

    public function taGradesReleased() {
        return $this->ta_grades_released;
    }

    public function hasConfig() {
        return $this->has_config;
    }

    public function inInteractiveQueue() {
        return $this->in_interactive_queue;
    }

    public function beingGradedInteractiveQueue() {
        return $this->grading_interactive_queue;
    }

    public function inBatchQueue() {
        return $this->in_batch_queue;
    }

    public function beingGradedBatchQueue() {
        return $this->grading_batch_queue;
    }

    public function getInteractiveQueuePosition() {
        return $this->interactive_queue_position;
    }

    public function getInteractiveQueueTotal() {
        return $this->interactive_queue_total;
    }

    public function getBatchQueuePosition() {
        return $this->batch_queue_position;
    }

    public function getBatchQueueTotal() {
        return $this->batch_queue_total;
    }

    public function getNumberOfGradingTotal() {
        return $this->grading_total;
    }

    public function isGradeByRegistration() {
        return $this->grade_by_registration;
    }

    /**
     * @return User
     */
    public function getUser() {
        return $this->user;
    }

    public function getCore() {
        return $this->core;
    }
}
