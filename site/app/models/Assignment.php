<?php

namespace app\models;

use app\exceptions\NotImplementedException;
use app\libraries\Core;
use app\libraries\DateUtils;
use app\libraries\FileUtils;
use app\libraries\Utils;

/**
 * Class Assignment
 *
 * Model of the current assignment being looked at for submission by the student. The information is a combination
 * of the info contained in the class.json file for the assignment as well as information solely in the assignment's
 * json file (as well as the relevant json files for the student's given attempt at the assignment. This does not
 * contain any information about the assignment that is stored in the database (so for example the TA grade details),
 * but only things contained in JSON/text files.
 */
class Assignment {

    /**
     * @var Core
     */
    private $core;

    private $details;
    private $assignment_id;
    private $assignment_name;

    private $submissions = 0;
    /**
     * The set active version for the assignment
     * @var int
     */
    private $active = -1;
    /**
     * The current version of the assignment being viewed
     * @var int
     */
    private $current = -1;
    /**
     * @var int Highest version submitted for an assignment
     */
    private $highest = 0;
    private $history = array();
    private $versions = array();
    
    /**
     * @var int Non hidden, non extra credit points
     */
    private $normal_points = 0;
    
    /**
     * @var int Non hidden points (including extra credit)
     */
    private $non_hidden_points = 0;
    
    /**
     * @var AssignmentTestcase[]
     */
    private $testcases = array();
    
    /**
     * Default max size allowed for upload if the field does exist in the config file for the assignment.
     * This value is in bytes.
     * @var int
     */
    private $default_max = 50000;
    
    /**
     * Array of all files for a specified submission number where each key is a previous file and then each element
     * is an array that contains filename, file path, and the file size.
     * @var array
     */
    private $submitted_files = array();
    private $meta_files = array();
    private $previous_files = array();

    private $result_details;

    private $svn_checkout = false;
    private $ta_grades_released = false;
    
    private $grade_file = null;
    
    public function __construct(Core $core, $assignment) {
        $this->core = $core;
        $this->details = $assignment;
        $this->assignment_id = $this->details['assignment_id'];
        $course_path = $this->core->getConfig()->getCoursePath();
        $this->details = array_merge($this->details, FileUtils::loadJsonFile($course_path."/config/".$this->assignment_id.
                                                                         "_assignment_config.json"));

        if (isset($this->details['assignment_name']) && $this->details['assignment_name'] !== "") {
            $this->assignment_name = $this->details['assignment_name'];
        }
        else {
            $this->assignment_name = $this->details['assignment_id'];
        }
        
        $this->svn_checkout = isset($this->details['svn_checkout']) ? $this->details['svn_checkout'] === true : false;
        $this->ta_grades_released = isset($this->details['ta_grade_released']) ? $this->details['ta_grade_released'] === true : false;
        
        if (isset($this->details['assignment_message'])) {
            $this->details['assignment_message'] = Utils::prepareHtmlMessage($this->details['assignment_message']);
        }
        
        if (!isset($this->details['num_parts'])) {
            $this->details['num_parts'] = 1;
        }
        
        if (!isset($this->details['max_submission_size'])) {
            $this->details['max_submission_size'] = $this->default_max;
        }
        else {
            $this->details['max_submission_size'] = floatval($this->details['max_submission_size']);
        }
        
        if (!isset($this->details['part_names']) || count($this->details['part_names']) < $this->details['num_parts']) {
            $parts = (isset($this->details['part_names'])) ? count($this->details['part_names']) : 0;
            for ($i = $parts; $i < $this->details['num_parts']; $i++) {
                $this->details['part_names'][] = 'Part '.($i + 1);
            }
        }
    
        if (isset($this->details['testcases'])) {
            foreach ($this->details['testcases'] as $testcase) {
                $testcase = new AssignmentTestcase($this->core, $testcase);
                $this->testcases[] = $testcase;
                $this->normal_points += $testcase->getNormalPoints();
                $this->non_hidden_points += $testcase->getNonHiddenPoints();
            }
        }

        $submission_path = $course_path."/submissions/".$this->assignment_id."/".$this->core->getUser()->getId();
        $results_path = $course_path."/results/".$this->assignment_id."/".$this->core->getUser()->getId();
        
        if (is_file($submission_path."/user_assignment_settings.json")) {
            $settings = FileUtils::loadJsonFile($submission_path."/user_assignment_settings.json");
            $this->active = $settings['active_version'];
            $this->history = $settings['history'];
        }

        $versions = array_map("intval", FileUtils::getAllDirs($submission_path));
        $temp = array_slice($versions, -1, 1);
        $this->highest = count($temp) > 0 ? intval(array_pop($temp)) : 0;
        foreach ($versions as $version) {
            $this->versions[$version] = FileUtils::loadJsonFile($results_path."/".$version."/submission.json");
            $this->versions[$version] = array_merge($this->versions[$version],
                                                    FileUtils::loadJsonFile($results_path."/".$version."/.grade.timestamp"));
            $this->versions[$version]['days_late'] = isset($this->versions[$version]['days_late_(before_extensions)']) ?
                intval($this->versions[$version]['days_late_(before_extensions)']) : 0;
            if ($this->versions[$version]['days_late'] < 0) {
                $this->versions[$version]['days_late'] = 0;
            }
            $this->versions[$version]['points'] = 0;
            // TODO: We don't want to take into account points_awarded for hidden testcases
            for ($i = 0; $i < count($this->testcases); $i++) {
                if (!$this->testcases[$i]->isHidden()) {
                    if ($this->versions[$version]['testcases'][$i]['points_awarded'] <= $this->testcases[$i]->getPoints()) {
                        $this->versions[$version]['points'] += $this->versions[$version]['testcases'][$i]['points_awarded'];
                    }
                    else {
                        $this->versions[$version]['points'] += $this->testcases[$i]->getPoints();
                    }
                }
            }
        }
        
        $this->submissions = count($this->versions);

        if ($this->active < 0 && $this->active > $this->submissions) {
            // TODO: What should happen here? Raise Exception;
            $this->active = $this->submissions;
        }

        if (isset($_REQUEST['assignment_version'])) {
            $this->current = intval($_REQUEST['assignment_version']);
        }

        if ($this->current < 0 && $this->active >= 0) {
            $this->current = $this->active;
        }
        else if ($this->current > $this->submissions) {
            $this->current = $this->active;
        }

        $submission_current_path = $submission_path."/".$this->current;
        
        $submitted_files = FileUtils::getAllFiles($submission_current_path, array(), true, true);
        foreach ($submitted_files as $file => $details) {
            if (substr(basename($file), 0, 1) === '.') {
                $this->meta_files[$file] = $details;
            }
            else {
                $this->submitted_files[$file] = $details;
            }
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
            $this->result_details = $this->versions[$this->current];
            for ($i = 0; $i < count($this->result_details['testcases']); $i++) {
                $this->testcases[$i]->addResultTestcase($this->result_details['testcases'][$i], $results_path."/".$this->current);
            }
        }
    
        // TODO: Get TA grade details
        
        $grade_file = $this->core->getConfig()->getCoursePath()."/reports/".$this->getAssignmentId()."/".$this->core->getUser()->getId().".txt";
        if (is_file($grade_file)) {
            $this->grade_file = htmlentities(file_get_contents($grade_file));
        }
        
    }

    public function getAssignmentId() {
        return $this->assignment_id;
    }

    public function getAssignmentName() {
        return $this->assignment_name;
    }

    public function getNumParts() {
        return $this->details['num_parts'];
    }

    public function getPartsNames() {
        return $this->details['part_names'];
    }

    public function getHighestVersion() {
        return $this->highest;
    }

    public function getActiveVersion() {
        return $this->active;
    }

    public function getCurrentVersion() {
        return $this->current;
    }

    public function getPreviousFiles($part = 1) {
        $part = ($this->getNumParts() < $part || $part < 1) ? 1 : $part;
        return $this->previous_files[$part];
    }
    
    public function getMaxSubmissions() {
        return $this->details['max_submissions'];
    }
    
    public function getSubmissionCount() {
        return $this->submissions;
    }
    
    public function getAllowedLateDays() {
        // TODO: Write the actual number to the class.json file and then return that value
        return 2;
    }
    
    /**
     * Returns the max size of the assignment, whether it was set in the config file or we're just the default
     * value. The returned value is in bytes.
     * @return int
     */
    public function getMaxSize() {
        return $this->details['max_submission_size'];
    }
    
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
    
    public function getTotalHiddenPoints() {
        throw new NotImplementedException();
    }
    
    public function getExtraCreditPoints() {
        throw new NotImplementedException();
    }
    
    public function getHiddenExtraCreditPoints() {
        throw new NotImplementedException();
    }
    
    public function getDueDate() {
        return $this->details['due_date'];
    }
    
    public function getDaysLate() {
        return ($this->hasCurrentResults()) ? $this->result_details['days_late'] : 0;
    }
    
    /**
     * Check to see if we have the result_details array from the results directory.
     * If false, we don't want to display any result details to the user about the
     * version.
     *
     * @return bool
     */
    public function hasCurrentResults() {
        return isset($this->result_details);
    }
    
    public function getCurrentResults() {
        return $this->result_details;
    }
    
    public function getSubmittedFiles() {
        return $this->submitted_files;
    }
    
    public function getTestcases() {
        return $this->testcases;
    }
    
    public function hasAssignmentMessage() {
        return isset($this->details['assignment_message']) && trim($this->details['assignment_message']) !== "";
    }
    
    public function getAssignmentMessage() {
        return isset($this->details['assignment_message']) ? $this->details['assignment_message'] : "";
    }
    
    public function useSvnCheckout() {
        return $this->svn_checkout;
    }
    
    public function hasGradeFile() {
        return $this->grade_file !== null;
    }
    
    public function getGradeFile() {
        return $this->grade_file;
    }
    
    public function taGradesReleased() {
        return $this->ta_grades_released;
    }
}