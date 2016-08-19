<?php

namespace app\models;

use \app\libraries\Core;
use app\libraries\GradeableType;

/**
 * Class GradeableDb
 *
 * Populates the Gradeable model by loading the data from the database
 */
class GradeableDb extends Gradeable {
    public function __construct(Core $core, $id) {
        parent::__construct($core, $id);
        
        $details = $this->core->getQueries()->getGradeableById($id);
        $timezone = new \DateTimeZone($this->core->getConfig()->getTimezone());
        $this->name = $details['g_title'];
        
        $this->ta_instructions = $details['g_overall_ta_instructions'];
        //$this->team_gradeable = isset($details['team-assignment']) ? $details['team-assignment'] === "yes" : "no";
    
        $this->type = $details['g_gradeable_type'];
        if ($this->type === GradeableType::ELECTRONIC_FILE) {
            $this->open_date = new \DateTime($details['eg_submission_open_date'], $timezone);
            $this->due_date = new \DateTime($details['eg_submission_due_date'], $timezone);
            $this->late_days = $details['eg_late_days'];
            $this->is_repository = $details['eg_is_repository'] === true;
            $this->subdirectory = $details['eg_subdirectory'];
            $this->point_precision = floatval($details['eg_precision']);
            $this->instructions_url = $details['eg_instructions_url'];
            $this->ta_grading = $details['eg_use_ta_grading'] === true;
            
            $this->loadGradeableConfig();
        }
        else if ($this->type === GradeableType::CHECKPOINTS) {
            //$this->optional_ta_message = $details['checkpt-opt-ta-messg'] === "yes";
            // TODO: load checkpoints
        }
        else {
            $this->type = GradeableType::NUMERIC_TEXT;
            // TODO: load numerics and text fields
        }
        
        $this->grade_by_registration = $details['g_grade_by_registration'] === true;
        $this->grade_start_date = new \DateTime($details['g_grade_start_date'], $timezone);
        $this->grade_released_date = new \DateTime($details['g_grade_released_date'], $timezone);
        // Is it past when the TA grades should be released
        $this->ta_grades_released = $this->grade_released_date < new \DateTime("now", $timezone);
        $this->bucket = $details['g_syllabus_bucket'];
    }
    
}