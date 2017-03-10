<?php

namespace app\models;

use \app\libraries\Core;
use app\libraries\DatabaseUtils;
use app\libraries\GradeableType;

/**
 * Class GradeableDb
 *
 * Populates the Gradeable model by loading the data from the database
 */
class GradeableDb extends Gradeable{
    public function __construct(Core $core, $details, $user=null) {
        parent::__construct($core, $details['g_id']);

        $this->user = ($user === null) ? $this->core->getUser() : $user;
        $this->gd_id = $details['gd_id'];
        $timezone = new \DateTimeZone($this->core->getConfig()->getTimezone());
        $this->name = $details['g_title'];
        
        $this->ta_instructions = $details['g_overall_ta_instructions'];
        $this->instructions_url = $details['g_instructions_url'];
        //$this->team_gradeable = isset($details['team-assignment']) ? $details['team-assignment'] === "yes" : "no";
    
        $this->type = $details['g_gradeable_type'];
        if ($this->type === GradeableType::ELECTRONIC_FILE) {
            $this->open_date = new \DateTime($details['eg_submission_open_date'], $timezone);
            $this->due_date = new \DateTime($details['eg_submission_due_date'], $timezone);
            $this->late_days = $details['eg_late_days'];
            $this->is_repository = $details['eg_is_repository'] === true;
            $this->subdirectory = $details['eg_subdirectory'];
            $this->point_precision = floatval($details['eg_precision']);
            $this->ta_grading = $details['eg_use_ta_grading'] === true;
            $this->grader_id = $details['gd_grader_id'];
            $this->overall_comment = $details['gd_overall_comment'];
            $this->status = $details['gd_status'];
            $this->graded_version = $details['gd_active_version'];

            if ($details['active_version'] !== null) {
                $this->been_autograded = true;
                $this->active_version = $details['active_version'];
                $this->graded_auto_non_hidden_non_extra_credit = floatval($details['autograding_non_hidden_non_extra_credit']);
                $this->graded_auto_non_hidden_extra_credit = floatval($details['autograding_non_hidden_extra_credit']);
                $this->graded_auto_hidden_non_extra_credit = floatval($details['autograding_hidden_non_extra_credit']);
                $this->graded_auto_hidden_extra_credit = floatval($details['autograding_hidden_extra_credit']);
                $this->submission_time =  new \DateTime($details['submission_time'], $timezone);
            }

            $this->total_tagrading_extra_credit = floatval($details['total_tagrading_extra_credit']);
            $this->total_tagrading_non_extra_credit = floatval($details['total_tagrading_non_extra_credit']);

            if (isset($details['graded_tagrading']) && $details['graded_tagrading'] !== null) {
                $this->been_tagraded = true;
                $this->graded_tagrading = $details['graded_tagrading'];
            }
            
            $this->loadGradeableConfig();
        }

        if (isset($details['array_gc_id'])) {
            $fields = array('gc_id', 'gc_title', 'gc_ta_comment', 'gc_student_comment', 'gc_max_value', 'gc_is_text',
                            'gc_is_extra_credit', 'gc_order', 'gcd_gc_id', 'gcd_score', 'gcd_component_comment');
            foreach ($fields as $key) {
                if (isset($details['array_'.$key])) {
                    $details['array_'.$key] = DatabaseUtils::fromPGToPHPArray($details['array_'.$key], true);
                }
            }
            for ($i = 0; $i < count($details['array_gc_id']); $i++) {
                $component_details = array('gcd_score' => 0, 'gcd_component_comment' => "");
                $component_fields = array('gc_id', 'gc_title', 'gc_ta_comment', 'gc_student_comment',
                                          'gc_max_value', 'gc_is_text', 'gc_is_extra_credit', 'gc_order');
                foreach ($component_fields as $key) {
                    $component_details[$key] = $details["array_{$key}"][$i];
                }

                if (isset($details['array_gcd_gc_id'])) {
                    for ($j = 0; $j < count($details['array_gcd_gc_id']); $j++) {
                        if ($details['array_gcd_gc_id'][$j] === $component_details['gc_id']) {
                            $component_details['gcd_score'] = $details['array_gcd_score'][$j];
                            $component_details['gcd_component_comment'] = $details['array_gcd_component_comment'][$j];
                            break;
                        }
                    }
                }
                $this->components[$component_details['gc_order']] = new GradeableComponent($component_details);
            }
        }
        
        $this->grade_by_registration = $details['g_grade_by_registration'] === true;
        $this->grade_start_date = new \DateTime($details['g_grade_start_date'], $timezone);
        $this->grade_released_date = new \DateTime($details['g_grade_released_date'], $timezone);
        $this->ta_view_date = new \DateTime($details['g_ta_view_start_date'], $timezone);
        // Is it past when the TA grades should be released
        $this->ta_grades_released = $this->grade_released_date < new \DateTime("now", $timezone);
        $this->bucket = $details['g_syllabus_bucket'];
    }
    
}