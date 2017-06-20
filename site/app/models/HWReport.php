<?php
namespace app\models;

use app\models\LateDaysCalculation;
use app\libraries\DatabaseUtils;
use app\libraries\Core; 

class HWReport extends AbstractModel {
    /*var Core */
    protected $core;
    
    public function __construct(Core $main_core) {
        $this->core = $main_core;
    }
    
    private function autogradingTotalAwarded($g_id, $student_id, $active_version){
        $total = 0;
        $results_file = implode(DIRECTORY_SEPARATOR, $this->core->getConfig()->getCoursePath(), "results", $g_id, $student_id, $active_version, "results.json");
        if (file_exists($results_file)) {
            $results_file_contents = file_get_contents($results_file);
            $results = json_decode($results_file_contents, true);
            if (isset($results['testcases'])) {
                foreach ($results['testcases'] as $testcase) {
                    $total += floatval($testcase['points_awarded']);
                }
            }
        }
        return $total;
    }
    
    private function getAutogradingMaxScore($g_id){
        $total = 0;
        $build_file = implode(DIRECTORY_SEPARATOR, $this->core->getConfig()->getCoursePath(), "config", "build","build_".$g_id.".json");
        if (file_exists($build_file)) {
            $build_file_contents = file_get_contents($build_file);
            $results = json_decode($build_file_contents, true);
            if (isset($results['testcases'])) {
                foreach ($results['testcases'] as $testcase) {
                    $testcase_value = floatval($testcase['points']);
                    if ($testcase_value > 0 && !$testcase['extra_credit']) {
                        $total += $testcase_value;
                    }
                }
            }
        }
        return $total;
    }
    
    private function generateReportModel($gradeable, $ldu) {
        if (!is_dir(implode(DIRECTORY_SEPARATOR, array($this->core->getConfig()->getCoursePath(), "reports")))) {
            mkdir(implode(DIRECTORY_SEPARATOR, array($this->core->getConfig()->getCoursePath(), "reports")));
        }
        $nl = "\n";
        $write_output = True;
        $g_id = $gradeable->getId();
        $rubric_total = 0;
        $ta_max_score = 0;
        // Gather student info, set output filename, reset output
        $student_output_text_main = "";
        $student_output_text = "";
        $student_final_output = "";
        $student_grade = 0;
        $grade_comment = "";
        
        $student_id = $gradeable->getUser()->getId();
        $student_output_filename = $student_id.".txt";
        $late_days_used_overall = 0;
        
        if($gradeable->beenTAgraded()) {
            $student_output_text_main .= strtoupper($gradeable->getTitle())." GRADE".$nl;
            $student_output_text_main .= "----------------------------------------------------------------------" . $nl;
            $firstname = $gradeable->getGrader()->getDisplayedFirstName();
            $student_output_text_main .= "Graded by: {$gradeable->getGrader()->getDisplayedFirstName()} {$gradeable->getGrader()->getLastName()} <{$gradeable->getGrader()->getEmail()}>".$nl;
            $late_days = $ldu->get_gradeable($student_id, $g_id);
            $student_output_text_main .= "Any regrade requests are due within 7 days of posting to: ".$gradeable->getGrader()->getEmail().$nl;
            if($gradeable->getDaysLate() > 0) {
                $student_output_text_main .= "This submission was submitted ".$gradeable->getDaysLate()." day(s) after the due date.".$nl;
            }
            if($late_days['extensions'] > 0) {
                $student_output_text_main .= "You have a ".$late_days['extensions']." day extension on this assignment.".$nl;
            }
            if($gradeable->getStatus() == 3 || $gradeable->getStatus() == 0) {
                $student_output_text_main .= "NOTE: THIS ASSIGNMENT WILL BE RECORDED AS ZERO".$nl;
                $student_output_text_main .= "Contact your TA or instructor if you believe this is an error".$nl;
            }
            if($late_days['late_days_charged'] > 0) {
                $student_output_text_main .= "Number of late days used for this homework: " . $late_days['late_days_charged'] . $nl;
            }
            $student_output_text_main .= "Total late days used this semester: " . $late_days['total_late_used'] . " (up to and including this assignment)" . $nl;
            $student_output_text_main .= "Late days remaining for the semester: " . $late_days['remaining_days'] . " (as of the due date of this homework)" . $nl;
            
            $student_output_text_main .= "----------------------------------------------------------------------" . $nl;
            $active_version = $gradeable->getActiveVersion();
            $submit_file = implode(DIRECTORY_SEPARATOR, array($this->core->getConfig()->getCoursePath(), "results", $gradeable['g_id'], $student_id, $active_version, "results_grade.txt"));
            $auto_grading_awarded = 0;
            $auto_grading_max_score = 0;
            if(!file_exists($submit_file)) {
                $student_output_text .= $nl.$nl."NO AUTO-GRADE RECORD FOUND (contact the instructor if you did submit this assignment)".$nl.$nl;
            }
            else {
                $auto_grading_awarded = $gradeable->getGradedAutoGraderPoints();
                $auto_grading_max_score = 0;
                foreach($gradeable->getComponents() as $component) {
                    $auto_grading_max_score += $component->getMaxValue();
                }
                $student_output_text .= "AUTO-GRADING TOTAL [ " . $auto_grading_awarded . " / " . $auto_grading_max_score . " ]" . $nl;
                $gradefilecontents = file_get_contents($submit_file);
                $student_output_text .= "submission version #" . $active_version .$nl;
                $student_output_text .= $nl.$gradefilecontents.$nl;
            }
            foreach($gradeable->getComponents() as $component) {
                $student_output_text .= $component->getTitle() . "[" . $component->getScore() . "/" . $component->getMaxValue() . "]".$nl;
                if($component->getStudentComment() != "") {
                    $student_output_text .= "Rubric: " . $component->getStudentComment() . $nl;
                }
                if($component->getComment() != "") {
                    $student_output_text .= "TA NOTE: " . $component->getComment() . $nl;
                }
                $student_output_text .= $nl;
                
                $student_grade += $component->getScore();
                if(!$component->isExtraCredit() && $component->getMaxValue() > 0) {
                    $rubric_total += $component->getMaxValue();
                    $ta_max_score += $component->getMaxValue();
                }
            }
            $student_output_text .= "TA GRADING TOTAL [ " . $student_grade . " / " . $ta_max_score . " ]". $nl;
            $student_output_text .= "----------------------------------------------------------------------" . $nl;
            $rubric_total += $auto_grading_max_score;
            $student_grade += $auto_grading_awarded;
            
            $student_final_grade = max(0,$student_grade);
            $student_output_last = strtoupper($gradeable->getTitle()) . " GRADE [ " . $student_final_grade . " / " . $rubric_total . " ]" . $nl;
            $student_output_last .= $nl;
            $student_output_last .= "OVERALL NOTE FROM TA: " . ($gradeable->getComment() != "" ? $gradeable->getComment() . $nl : "No Note") . $nl;
            $student_output_last .= "----------------------------------------------------------------------" . $nl;
            
            $student_final_output = $student_output_text_main . $student_output_text. $student_output_last;
        }
        else {
            $student_final_output = "[ TA HAS NOT GRADED ASSIGNMENT, CHECK BACK LATER ]";
        }
        $dir = implode(DIRECTORY_SEPARATOR, array($this->core->getConfig()->getCoursePath(), "reports", $g_id));
        if (!is_dir($dir)) {
            if(!mkdir($dir)) {
                print "failed to create directory {$dir}";
                exit();
            }
        }
        $save_filename = implode(DIRECTORY_SEPARATOR, array($dir, $student_output_filename));
        if(file_put_contents($save_filename, $student_final_output) === false) {
            print "failed to write {$save_filename}\n";
        }
    }
    
    private function generateReport($gradeable, $ldu, $graders){
        // Make sure we actually have a created output directory
        if (!is_dir(implode(DIRECTORY_SEPARATOR, array($this->core->getConfig()->getCoursePath(), "reports")))) {
            mkdir(implode(DIRECTORY_SEPARATOR, array($this->core->getConfig()->getCoursePath(), "reports")));
        }
        $nl = "\n";
        $write_output = True;
        $g_id = $gradeable['g_id'];
        $rubric_total = 0;
        $ta_max_score = 0;
        // Gather student info, set output filename, reset output
        $student_output_text_main = "";
        $student_output_text = "";
        $student_grade = 0;
        $grade_comment = "";

        $student_id = $gradeable["user_id"];
        $student_first_name = $gradeable["user_firstname"];
        $student_last_name = $gradeable["user_lastname"];
        $student_output_filename = $student_id . ".txt";
        $student_section = intval($gradeable['registration_section']);
        $late_days_used_overall = 0;

        // Check to see if student has been graded yet
        $graded=true;
        if(isset($gradeable['score']) && $gradeable["score"] != -100 && isset($gradeable["gd_grader_id"])) {
            $grade_user_id = $gradeable["gd_grader_id"];
            $grade_comment = htmlspecialchars($gradeable["gd_overall_comment"]);
            // Query database to gather TA info

            // Generate output
            $student_output_text_main .= strtoupper($gradeable['g_title']) . " GRADE" . $nl;
            $student_output_text_main .= "----------------------------------------------------------------------" . $nl;
            if (isset($gradeable['grader_id']) && $gradeable['grader_group'] < 3) {
                $firstname = $gradeable['grader_firstname'];
                if (isset($gradeable['grader_preferred']) && $gradeable['grader_preferred'] !== "") {
                    $firstname = $gradeable['grader_preferred'];
                }
                $student_output_text_main .= "Graded by: {$firstname} {$gradeable['grader_lastname']} <{$gradeable['grader_email']}>" . $nl;
            }

            $late_days = $ldu->get_gradeable($student_id, $g_id);

            if (isset($gradeable['grader_id'])) {
                $student_output_text_main .= "Any regrade requests are due within 7 days of posting to: " . $gradeable['grader_email'] . $nl;
            }
            else {
                $student_output_text_main .= "Any regrade requests are due within 7 days of posting";
            }


            if ($late_days['late_days_used'] > 0) {
                $student_output_text_main .= "This submission was " . $late_days['late_days_used'] . " day(s) after the due date." . $nl;
            }
            if ($late_days['extensions'] > 0) {
                $student_output_text_main .= "You have a " . $late_days['extensions'] . " day extension on this assignment." . $nl;
            }
            $student_output_text_main .= "Homework status: " . $late_days['status'] . $nl;
            if (strpos($late_days['status'], 'Bad') !== false) {
                $student_output_text_main .= "NOTE:  HOMEWORK GRADE WILL BE RECORDED AS ZERO.";
                $student_output_text_main .= "  Contact your TA or instructor if you believe this is an error." . $nl;
            }
            if ($late_days['late_days_charged'] > 0) {
                $student_output_text_main .= "Number of late days used for this homework: " . $late_days['late_days_charged'] . $nl;
            }
            $student_output_text_main .= "Total late days used this semester: " . $late_days['total_late_used'] . " (up to and including this assignment)" . $nl;
            $student_output_text_main .= "Late days remaining for the semester: " . $late_days['remaining_days'] . " (as of the due date of this homework)" . $nl;


            $student_output_text_main .= "----------------------------------------------------------------------" . $nl;
            $grading_notes = DatabaseUtils::fromPGToPHPArray($gradeable['comments']);
            $question_totals = DatabaseUtils::fromPGToPHPArray($gradeable['scores']);
            $question_messages = DatabaseUtils::fromPGToPHPArray($gradeable['titles']);
            $question_extra_credits = DatabaseUtils::fromPGToPHPArray($gradeable['is_extra_credits'], true);
            $question_grading_notes = DatabaseUtils::fromPGToPHPArray($gradeable['grading_notes']);
            $question_max_scores = DatabaseUtils::fromPGToPHPArray($gradeable['max_scores']);
            $question_total = 0;

            $active_version = $gradeable['gd_active_version'];
            $submit_file = implode(DIRECTORY_SEPARATOR, array($this->core->getConfig()->getCoursePath(), "results", $gradeable['g_id'], $student_id, $active_version, "results_grade.txt"));
            $auto_grading_max_score = 0;
            $auto_grading_awarded = 0;
            if (!file_exists($submit_file)) {
                $student_output_text .= $nl.$nl."NO AUTO-GRADE RECORD FOUND (contact the instructor if you did submit this assignment)".$nl.$nl;
            }
            else {
                $auto_grading_awarded = $this->autogradingTotalAwarded($gradeable['g_id'], $student_id, $active_version);
                $auto_grading_max_score = $this->getAutogradingMaxScore($gradeable['g_id']);
                $student_output_text .= "AUTO-GRADING TOTAL [ " . $auto_grading_awarded . " / " . $auto_grading_max_score . " ]" . $nl;
                $gradefilecontents = file_get_contents($submit_file);
                $student_output_text .= "submission version #" . $active_version .$nl;
                $student_output_text .= $nl.$gradefilecontents.$nl;
            }
            for ($i = 0; $i < count($grading_notes); $i++){
                $grading_note = $grading_notes[$i];
                $question_total = floatval($question_totals[$i]);
                $question_max_score = floatval($question_max_scores[$i]);
                $question_message = $question_messages[$i];
                $question_extra_credit = boolval($question_extra_credits[$i]);
                $question_grading_note = $question_grading_notes[$i];
                // ensure we have indexes for this part
                if($question_total == -100){
                    $grade_question_score = "ERROR";
                    $grade_question_comment = "[ ERROR GRADE MISSING ]";
                    // Found error, subtract 1 million to ensure we catch the bad grade
                    $student_grade -= 1000000;
                }
                else{
                    $grade_question_score = floatval($question_total);
                    $grade_question_comment = htmlspecialchars($grading_note);
                }
                // Generate output for each question
                $student_output_text .= $question_message . " [ " . $grade_question_score . " / " . $question_max_score . " ]" . $nl;
                // general rubric notes intended for TA & student
                if ($question_grading_note != "") {
                    $student_output_text .= "Rubric: ". $question_grading_note . $nl;
                }
                if ($grade_question_comment != "") {
                    $student_output_text .= $nl."   TA NOTE: " . $grade_question_comment . $nl;
                }
                $student_output_text .= $nl;
                // Keep track of students grade and rubric total
                $student_grade += $grade_question_score;
                if (!$question_extra_credit && $question_max_score > 0){
                    $rubric_total += $question_max_score;
                    $ta_max_score += $question_max_score;
                }
            }

            $student_output_text .= "TA GRADING TOTAL [ " . $student_grade . " / " . $ta_max_score . " ]". $nl;
            $student_output_text .= "----------------------------------------------------------------------" . $nl;
            $rubric_total += $auto_grading_max_score;
            $student_grade += $auto_grading_awarded;
        }

        if($write_output){
            $student_final_grade = max(0,$student_grade);
            $student_output_last = strtoupper($gradeable['g_title']) . " GRADE [ " . $student_final_grade . " / " . $rubric_total . " ]" . $nl;
            $student_output_last .= $nl;
            $student_output_last .= "OVERALL NOTE FROM TA: " . ($grade_comment != "" ? $grade_comment . $nl : "No Note") . $nl;
            $student_output_last .= "----------------------------------------------------------------------" . $nl;
            $dir = implode(DIRECTORY_SEPARATOR, array($this->core->getConfig()->getCoursePath(), "reports", $gradeable['g_id']));

            if (!is_dir($dir)) {
                if(!mkdir($dir)) {
                    print "failed to create directory {$dir}";
                    exit();
                }
            }
            
            $save_filename = implode(DIRECTORY_SEPARATOR, array($dir, $student_output_filename));
            $student_final_output = $student_output_text_main . $student_output_text. $student_output_last;
            if ($student_final_output == "") {
                $student_final_output = "[ TA HAS NOT GRADED ASSIGNMENT, CHECK BACK LATER ]";
            }
            if (file_put_contents($save_filename, $student_final_output) === false) {
                print "failed to write {$save_filename}\n";
            }
        }
    }
    
    public function generateAllReportsModels() {
        $students = $this->core->getQueries()->getAllUsers();
        $stu_ids = array_map(function($stu) {return $stu->getId();}, $students);
        $gradeables = $this->core->getQueries()->getGradeables(null, $stu_ids);
        $graders = $this->core->getQueries()->getAllGraders();
        $ldu = new LateDaysCalculation($this->core);
        foreach($gradeables as $gradeable) {
            if($gradeable->getGrader() == null) {
                foreach($graders as $g) {
                    if($g->getId() == $gradeable->getGraderId()) {
                        $gradeable->setGrader($g);
                    }
                }
            }
            $this->generateReportModel($gradeable, $ldu);
        }
    }
    
    public function generateSingleReportModels($student_id, $gradeable_id) {
        $gradeables = $this->core->getQueries()->getGradeables($gradeable_id, $student_id);
        $graders = $this->core->getQueries()->getAllGraders();
        $ldu = new LateDaysCalculation($this->core);
        foreach($gradeables as $gradeable) {
            if($gradeable->getGrader() == null) {
                foreach($graders as $grader) {
                    if($grader->getId() == $gradeable->getGraderId()) {
                        $gradeable->setGrader($grader);
                    }
                }
            }
            $this->generateReportModel($gradeable, $ldu);
        }
    }
    
    public function generateAllReportsForGradeableModels($g_id) {
        $students = $this->core->getQueries()->getAllUsers();
        $stu_ids = array_map(function($stu) {return $stu->getId();}, $students);
        $gradeables = $this->core->getQueries()->getGradeables($g_id, $stu_ids);
        $graders = $this->core->getQueries()->getAllGraders();
        $ldu = new LateDaysCalculation($this-core);
        foreach($gradeables as $gradeable) {
            if($gradeable->getGrader() == null) {
                foreach($graders as $grader) {
                    if($grader->getId() == $gradeable->getGraderId()) {
                        $gradeable->setGrader($grader);
                    }
                }
            }
            $this->generateReportModel($gradeable, $ldu);
        }
    }
    
    public function generateAllReportsForStudentModels($stu_id) {
        $gradeables = $this->core->getQueries()->getGradeables(null, $stu_id);
        $graders = $this->core->getQueries()->getAllGraders();
        $ldu = new LateDaysCalculation($this->core);
        foreach($gradeables as $gradeable) {
            if($gradeable->getGrader() == null) {
                foreach($graders as $grader) {
                    if($grader->getId() == $gradeable->getGraderId()) {
                        $gradeable->setGrader($grader);
                    }
                }
            }
            $this->generateReportModel($gradeable, $ldu);
        }
    }
    //////////////////////////////////////////////////////////////////////////
    public function generateAllReports() {
        $reportsData = $this->core->getQueries()->getReportData();
        $graders = $this->core->getQueries()->getGraders();
        $ldu = new LateDaysCalculation($this->core);

        foreach ($reportsData as $report) {
            $this->generateReport($report, $ldu, $graders);
        }
    }
    
    public function generateSingleReport($student_id, $gradeable_id) {
        $reportData = $this->core->getQueries()->getReportData(array('g_id'=>$gradeable_id, 'u_id'=>$student_id));
        $graders = $this->core->getQueries()->getGraders();
        $ldu = new LateDaysCalculation($this->core);
        
        foreach($reportData as $report) {
            $this->generateReport($report, $ldu, $graders);
        }
    }
    
    public function generateAllReportsForStudent($student_id) {
        $reportsData = $this->core->getQueries()->getReportData(array('u_id' => $student_id));
        $graders = $this->core->getQueries()->getGraders();
        $ldu = new LateDaysCalculation($this->core);

        foreach ($reportsData as $report) {
            $this->generateReport($report, $ldu, $graders);
        }
    }
    
    public function generateAllReportsForGradeable($g_id) {
        $reportsData = $this->core->getQueries()->getReportData(array('g_id' => $g_id));
        $graders = $this->core->getQueries()->getGraders();
        $ldu = new LateDaysCalculation($this->core);
        
        foreach($reportsData as $report) {
            $this->generateReport($report, $ldu, $graders);
        }
    }
}
?>