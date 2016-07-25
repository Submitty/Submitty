<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\FileUtils;
use app\libraries\Utils;

/**
 * Class ClassJson
 *
 * Model representing the class.json that exists for any given course. Additionally, it contains a model
 * for the current assignment that is being looked at by the client (either latest assignment or one
 * specified by the client). This model is then used to build the Submission page for students.
 */
class ClassJson {
    /**
     * @var Core
     */
    private $core;

    private $class;
    
    /**
     * @var array()
     */
    private $allowed_assignments = null;

    /**
     * @var Assignment
     */
    private $assignment = null;

    public function __construct(Core $core, $assignment = null) {
        $this->core = $core;
        $this->class = FileUtils::loadJsonFile($this->core->getConfig()->getCoursePath()."/config/class.json");
        $this->class['ta_grades'] = isset($this->class['ta_grades']) ?
            $this->class['ta_grades'] !== false : true;
        $this->class['grade_summary'] = isset($this->class['grade_summary']) ?
            $this->class['grade_summary'] !== false : true;
        $this->class['download_files'] = isset($this->class['download_files']) ?
            $this->class['download_files'] === true : false;
        $this->class['upload_message'] = isset($this->class['upload_message']) ?
            Utils::prepareHtmlMessage($this->class['upload_message']) : "";
        
        $this->getAssignments();
        
        if ($assignment === null || !array_key_exists($assignment, $this->allowed_assignments)) {
            $array = array_slice($this->allowed_assignments, -1);
            $assignment = array_pop($array);
        }
        else {
            $assignment = $this->allowed_assignments[$assignment];
        }
        
        if ($assignment !== null) {
            $this->assignment = new Assignment($this->core, $assignment);
        }
    }

    public function getAllAssignments() {
        return $this->class['assignments'];
    }
    
    /**
     * Returns an array containing all assignment_ids that the logged in user is allowed to acccess, whether
     * the assignment has been released or the user is an admin (and then can see all assignments regardless
     * of whether they've been released or not.
     *
     * @return array
     */
    public function getAssignments() {
        if ($this->allowed_assignments === null) {
            $this->allowed_assignments = array();
            foreach ($this->getAllAssignments() as $assignment) {
                if (!isset($assignment['assignment_id']) ||
                    array_key_exists($assignment['assignment_id'], $this->allowed_assignments)) {
                    continue;
                }
                if ($this->core->getUser()->accessAdmin() || $assignment['released'] === true) {
                    $this->allowed_assignments[$assignment['assignment_id']] = $assignment;
                }
            }
        }
        return $this->allowed_assignments;
    }

    /**
     * @return Assignment
     */
    public function getCurrentAssignment() {
        return $this->assignment;
    }
    
    public function getUploadMessage() {
        return isset($this->class['upload_message']) ? $this->class['upload_message'] : "";
    }
    
    public function showTaGrades() {
        return $this->class['ta_grades'];
    }
    
    public function showGradeSummary() {
        return $this->class['grade_summary'];
    }
    
    public function downloadFiles() {
        return $this->class['download_files'];
    }
}