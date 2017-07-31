<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\DatabaseUtils;
use app\libraries\FileUtils;
use app\libraries\GradeableType;

class GradeSummary extends AbstractModel {
    /**/
    protected $core;
    
    public function __construct(Core $main_core) {
        $this->core = $main_core;
    }

    public function generateSummariesFromQueryResults($summary_data, $ldu) {
        /* Array of Students, indexed by user_id
            Each index contains an array indexed by syllabus 
            bucket which contain all assignments in the respective syllabus bucket 
        */
        $student_output_json = array();
        $buckets = array();
        foreach ($summary_data as $gradeable) {
            $student_id = $gradeable->getUser()->getId();
            if(!isset($buckets[ucwords($gradeable->getSyllabusBucket())])) {
                $buckets[ucwords($gradeable->getSyllabusBucket())] = true;
            }
            if(!array_key_exists($student_id, $student_output_json)) {
                $student_output_json[$student_id] = array();

                // CREATE HEADER FOR JSON
                $student_output_json[$student_id]["user_id"] = $student_id;
                $student_output_json[$student_id]["legal_first_name"] = $gradeable->getUser()->getFirstName();
                $student_output_json[$student_id]["preferred_first_name"] = $gradeable->getUser()->getPreferredFirstName();
                $student_output_json[$student_id]["last_name"] = $gradeable->getUser()->getLastName();
                $student_output_json[$student_id]["registration_section"] = $gradeable->getUser()->getRegistrationSection();

                $student_output_json[$student_id]["default_allowed_late_days"] = $this->core->getConfig()->getDefaultStudentLateDays();
                //$student_output_json["allowed_late_days"] = $late_days_allowed;

                $student_output_json[$student_id]["last_update"] = date("l, F j, Y");
                foreach($buckets as $category => $bucket) {
                    $student_output_json[$student_id][ucwords($category)] = array();
                }
            }
            if(!isset($student_output_json[$student_id][ucwords($gradeable->getSyllabusBucket())])) {
                $student_output_json[$student_id][ucwords($gradeable->getSyllabusBucket())] = array();
            }
            $student = $student_output_json[$student_id];

            $student_output_json[$student_id] = $this->generateSummary($gradeable, $ldu, $student);
        }
        
        // WRITE THE JSON FILE
        foreach($student_output_json as $student) {
            $student_id = $student['user_id'];
            $student_output_json_name = $student_id . "_summary.json";

            file_put_contents(implode(DIRECTORY_SEPARATOR, array($this->core->getConfig()->getCoursePath(), "reports","all_grades", $student_output_json_name)), json_encode($student,JSON_PRETTY_PRINT));

        }
    }
    
    private function generateSummary($gradeable, $ldu, $student) {
        $this_g = array();
        
        $autograding_score = $gradeable->getGradedAutoGraderPoints();
        $ta_grading_score = $gradeable->getGradedTAPoints();

        $this_g['id'] = $gradeable->getId();
        $this_g['name'] = $gradeable->getName();
        $this_g['grade_released_date'] = $gradeable->getGradeReleasedDate();
        
        if($gradeable->validateVersions() || !$gradeable->useTAGrading()){
            $this_g['score'] = max(0,floatval($autograding_score)+floatval($ta_grading_score));
        }
        else{
            $this_g['score'] = 0;
            $this_g['note'] = 'SCORE IS SET TO 0 BECAUSE THERE ARE VERSION CONFLICTS.';
            $this_g['active_version'] = $gradeable->getActiveVersion();
            $this_g['graded_versions'] = $gradeable->printVersions();
        }
        
        switch ($gradeable->getType()) {
            case GradeableType::ELECTRONIC_FILE:
                $this->addLateDays($this_g, $ldu, $gradeable);
                $this->addText($this_g, $gradeable);
                break;
            case GradeableType::NUMERIC_TEXT:
                $this->addText($this_g, $gradeable);
                $this->addProblemScores($this_g, $gradeable);
                break;
            case GradeableType::CHECKPOINTS:
                $this->addProblemScores($this_g, $gradeable);
                break;
        }
        array_push($student[ucwords($gradeable->getSyllabusBucket())], $this_g);

        return $student;
    }
    
    private function addLateDays(&$this_g, $ldu, $gradeable) {
        $late_days = $ldu->getGradeable($gradeable->getUser()->getId(), $gradeable->getId());

        if(substr($late_days['status'], 0, 3) == 'Bad') {
            $this_g["score"] = 0;
        }
        $this_g['status'] = $late_days['status'];

        if (array_key_exists('late_days_charged', $late_days) && $late_days['late_days_used'] > 0) {

            // TODO:  DEPRECATE THIS FIELD
            $this_g['days_late'] = $late_days['late_days_charged'];

            // REPLACED BY:
            $this_g['days_after_deadline'] = $late_days['late_days_used'];
            $this_g['extensions'] = $late_days['extensions'];
            $this_g['days_charged'] = $late_days['late_days_charged'];

        }
        else {
            $this_g['days_late'] = 0;
        }
    }
    
    private function addText(&$this_g, $gradeable) {
        $text_items = array();
        foreach($gradeable->getComponents() as $component) {
            array_push($text_items, array($component->getTitle() => $component->getComment()));
        }

        if(count($text_items) > 0){
            $this_g["text"] = $text_items;
        }
    }
    
    private function addProblemScores(&$this_g, $gradeable) {
        $component_scores = array();
        foreach($gradeable->getComponents() as $component) {
            array_push($component_scores, array($component->getTitle() => $component->getScore()));
        }
        $this_g["component_scores"] = $component_scores;
    }
    
    public function generateAllSummaries() {
        $users = $this->core->getQueries()->getAllUsers();
        $ids = array_map(function($user) {return $user->getId();}, $users);
        $summary_data = $this->core->getQueries()->getGradeables(null, $ids);
        $ldu = new LateDaysCalculation($this->core);

        $this->generateSummariesFromQueryResults($summary_data, $ldu);
    }
    
    public function generateAllSummariesForStudent($student_id) {
        $summary_data = $this->core->getQueries()->getGradeables(null, $student_id);
        $ldu = new LateDaysCalculation($this->core);

        $this->generateSummariesFromQueryResults($summary_data, $ldu);
    }
}

