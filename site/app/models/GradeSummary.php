<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\DatabaseUtils;
use app\libraries\FileUtils;
use app\libraries\GradeableType;
use app\libraries\Logger;

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
        Logger::debug("Called generateSummariesFromQueryResults");
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

            Logger::debug("User: ". $gradeable->getUser()->getId() . " GradeableID: ". $gradeable->getId());

            $student_output_json[$student_id] = $this->generateSummary($gradeable, $ldu, $student);
        }
        
        // WRITE THE JSON FILE
        foreach($student_output_json as $student) {
            $student_id = $student['user_id'];
            $student_output_json_name = $student_id . "_summary.json";

            file_put_contents(implode(DIRECTORY_SEPARATOR, array($this->core->getConfig()->getCoursePath(), "reports","all_grades", $student_output_json_name)), json_encode($student,JSON_PRETTY_PRINT));

        }
    }

    public function generateSummariesFromQueryResultsBuster(&$student_output_json, &$buckets, $summary_data, $ldu) {
        /* Array of Students, indexed by user_id
            Each index contains an array indexed by syllabus
            bucket which contain all assignments in the respective syllabus bucket
        */
        Logger::debug("Called generateSummariesFromQueryResults");
        //$student_output_json = array();
        //$buckets = array();
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

            Logger::debug("User: ". $gradeable->getUser()->getId() . " GradeableID: ". $gradeable->getId());

            $student_output_json[$student_id] = $this->generateSummary($gradeable, $ldu, $student);
        }


    }


    
    private function generateSummary($gradeable, $ldu, $student) {
        Logger::debug("Called generateSummary");
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
            if($gradeable->validateVersions(-1)) {
                $this_g['note'] = 'This has not been graded yet.';
            }
            else if($gradeable->getActiveVersion() !== 0) {
                $this_g['note'] = 'Score is set to 0 because there are version conflicts.';
            }
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
        //ini_set("memory_limit","512M");
        Logger::debug("Called generateAllSummaries");
        Logger::debug("Here's a second message");
        $users = $this->core->getQueries()->getAllUsers();
        Logger::debug("Got All users");
        $ids = array_map(function($user) {return $user->getId();}, $users);
        Logger::debug("array_map called");
        $gradeable_ids = $this->core->getQueries()->getAllGradeablesIds();
        $gradeable_ids = array_map(function($g_id) { return $g_id["g_id"];}, $gradeable_ids);
        //$gradeable_ids = array_map(function($g_id) { return print_r($g_id["g_id"],true);}, $gradeable_ids);
        Logger::debug("got gradeable IDS");
        //Logger::debug("They are:".implode(",",$gradeable_ids));
        $n_users = count($ids);
        $n_gradeable_ids = count($gradeable_ids);
        Logger::debug("There are {$n_users} users and {$n_gradeable_ids} gradeable IDs");

/*
        $summary_data = $this->core->getQueries()->getGradeables(null, $ids);
        Logger::debug("Got gradeables");
        $ldu = new LateDaysCalculation($this->core);
        Logger::debug("Made LDU");

        $this->generateSummariesFromQueryResults($summary_data, $ldu);
*/

        $ldu = new LateDaysCalculation($this->core);
        $student_output_json = array();
        $buckets = array();
        Logger::debug("Made LDU");
        foreach($gradeable_ids as $g_id){
            $summary_data = $this->core->getQueries()->getGradeables($g_id,$ids);
            Logger::debug("Got gradeable {$g_id}");
            $this->generateSummariesFromQueryResultsBuster($student_output_json,$buckets,$summary_data,$ldu);
            //$ldu = new LateDaysCalculation($this->core); //Do we need a new one each time? Not sure.
        }

        Logger::debug("Writing reports");
        // WRITE THE JSON FILE
        foreach($student_output_json as $student) {
            $student_id = $student['user_id'];
            $student_output_json_name = $student_id . "_summary.json";

            file_put_contents(implode(DIRECTORY_SEPARATOR, array($this->core->getConfig()->getCoursePath(), "reports","all_grades", $student_output_json_name)), json_encode($student,JSON_PRETTY_PRINT));

        }
    }
    
    public function generateAllSummariesForStudent($student_id) {
        $summary_data = $this->core->getQueries()->getGradeables(null, $student_id);
        $ldu = new LateDaysCalculation($this->core);

        $this->generateSummariesFromQueryResults($summary_data, $ldu);
    }
}

