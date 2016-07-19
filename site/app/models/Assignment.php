<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\FileUtils;

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

    private $submissions = 0;
    private $active = -1;
    private $current = -1;
    private $history = array();
    private $versions = array();
    
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

    private $result_details = array();

    public function __construct(Core $core, $assignment) {
        $this->core = $core;
        $this->details = $assignment;
        $this->assignment_id = $this->details['assignment_id'];
        $course_path = $this->core->getConfig()->getCoursePath();
        $this->details = array_merge($this->details, FileUtils::loadJsonFile($course_path."/config/".$this->assignment_id.
                                                                         "_assignment_config.json"));

        if (!isset($this->details['num_parts'])) {
            $this->details['num_parts'] = 1;
        }
        
        if (!isset($this->details['part_names']) || count($this->details['part_names']) < $this->details['num_parts']) {
            $parts = (isset($this->details['part_names'])) ? count($this->details['part_names']) : 0;
            for ($i = $parts; $i < $this->details['num_parts']; $i++) {
                $this->details['part_names'][] = 'Part '.($i + 1);
            }
        }

        $submission_path = $course_path."/submissions/".$this->assignment_id."/".$this->core->getUser()->getUserId();
        if (is_file($submission_path."/user_assignment_settings.json")) {
            $settings = FileUtils::loadJsonFile($submission_path."/user_assignment_settings.json");
            $this->active = $settings['active_assignment'];
            $this->history = $settings['history'];
        }

        $this->versions = FileUtils::getAllDirs($submission_path);
        $this->submissions = count($this->versions);

        if ($this->active < 0 && $this->active > $this->submissions) {
            // TODO: What should happen here? Raise Exception;
            $this->active = $this->submissions;
        }

        if (isset($_REQUEST['assignment_version'])) {
            $this->current = $_REQUEST['assignment_version'];
        }

        if ($this->current == 0 && $this->active > 0) {
            $this->current = $this->active;
        }
        else if ($this->current > $this->submissions) {
            $this->current = $this->active;
        }

        $submission_path .= "/".$this->current;

        for ($i = 0; $i <= $this->details['num_parts']; $i++) {
            $this->submitted_files[$i] = array();
        }
        if ($this->details['num_parts'] > 1) {
            for ($i = 1; $i <= $this->details['num_parts']; $i++) {
                $submitted_files = FileUtils::getAllFiles($submission_path."/{$i}");
                if (count($submitted_files) > 0) {
                    foreach ($submitted_files as $file) {
                        $this->submitted_files[$i][basename($file)] = array('name' => basename($file),
                                                                            'full_path' => $file,
                                                                            'size' => filesize($file));
                    }
                }
            }
        }
        else {
            $submitted_files = FileUtils::getAllFiles($submission_path);
            foreach ($submitted_files as $file) {
                $this->submitted_files[1][basename($file)] = array('name' => basename($file), 'full_path' => $file,
                                                                   'size' => filesize($file));
            }
        }

        $result_path = $course_path."/results/".$this->assignment_id."/".$this->core->getUser()->getUserId()."/".$this->current;
        if (is_file($result_path."/submission.json")) {
            $this->result_details = FileUtils::loadJsonFile($result_path . "/submission.json");
        }

        // TODO: Get TA grade details
    }

    public function getAssignmentId() {
        return $this->assignment_id;
    }

    public function getAssignmentName() {
        return $this->details['assignment_name'];
    }

    public function getNumParts() {
        return $this->details['num_parts'];
    }

    public function getPartsNames() {
        return $this->details['part_names'];
    }

    public function getHighestVersion() {
        $array = array_slice($this->versions, -1, 1, true);
        return (count($array) > 0) ? array_pop($array) : 0;
    }

    public function getActiveVersion() {
        return $this->active;
    }

    public function getCurrentVersion() {
        return $this->current;
    }

    public function getPreviousFiles($part = 1) {
        $part = ($this->details['num_parts'] < $part || $part < 1) ? 1 : $part;
        return $this->submitted_files[$part];
    }
    
    public function getMaxSubmissions() {
        return $this->details['max_submissions'];
    }
    
    /**
     * Returns the number of days between the due date of the assignment and the current time
     * showing the possible number of late days used if the assignment were to be submitted.
     *
     * @return int
     */
    public function getPossibleDaysLate() {
        $due_date = new \DateTime($this->details['due_date']);
        $now = new \DateTime("NOW");
        $due_date->sub(new \DateInterval("P1D"));  // ceiling up late days
        return intval(date_diff($due_date, $now)->format('%r%a'));
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
}