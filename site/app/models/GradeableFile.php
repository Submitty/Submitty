<?php

namespace app\models;

use \app\libraries\Core;
use \app\libraries\FileUtils;
use \app\libraries\GradeableType;

/**
 * Class GradeableFile
 *
 * Populates the Gradeable model by using the saved JSON files in the
 * config/form directory in the course path
 */
class GradeableFile extends Gradeable {
    public function __construct(Core $core, $id) {
        parent::__construct($core, $id);
        $timezone = new \DateTimeZone($this->core->getConfig()->getTimezone());
        
        $details = FileUtils::readJsonFile($this->core->getConfig()->getCoursePath()."/config/form/form_".$id.".json");
        $this->name = $details['gradeable_title'];
        
        $this->ta_instructions = $details['ta_instructions'];
        //$this->team_gradeable = isset($details['team-assignment']) ? $details['team-assignment'] === "yes" : "no";
    
        if ($details['gradeable_type'] == "Electronic File") {
            $this->type = GradeableType::ELECTRONIC_FILE;
            $this->open_date = new \DateTime($details['date_submit'], $timezone);
            $this->due_date = new \DateTime($details['date_due'], $timezone);
            $this->late_days = $details['eg_late_days'];
            $this->is_repository = $details['upload_type'] === "Repository";
            $this->subdirectory = isset($details['subdirectory']) ? $details['subdirectory'] : "";
            $this->point_precision = floatval($details['point_precision']);
            $this->instructions_url = $details['instructions_url'];
            $this->ta_grading = $details['ta_grading'] == "yes";
            
            $this->loadGradeableConfig();
        }
        else if ($details['gradeable_type'] == "Checkpoints") {
            $this->type = GradeableType::CHECKPOINTS;
            $this->optional_ta_message = $details['checkpt-opt-ta-messg'] === "yes";
            // TODO: load checkpoints
        }
        else {
            $this->type = GradeableType::NUMERIC;
            // TODO: load numerics and text fields
        }
        
        $this->grade_by_registration = $details['section_type'] === "reg-section";
        $this->grade_start_date = new \DateTime($details['date_grade'], $timezone);
        $this->grade_released_date = new \DateTime($details['date_released'], $timezone);
        // Is it past when the TA grades should be released
        $this->ta_grades_released = $this->grade_released_date < new \DateTime("now", $timezone);
        $this->bucket = $details['gradeable_buckets'];
    }
}