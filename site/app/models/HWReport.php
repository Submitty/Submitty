<?php
namespace app\models;

use app\models\LateDaysCalculation;
use app\libraries\DatabaseUtils;
use app\libraries\Core;
use app\libraries\FileUtils; 

class HWReport extends AbstractModel {
    /*var Core */
    protected $core;
    
    public function __construct(Core $main_core) {
        $this->core = $main_core;
    }
    
    private function generateReport($gradeable, $ldu) {
        // Make sure we have a good directory
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
        
        // Only generate full report when the TA has graded the work, may want to change
        if($gradeable->beenTAgraded()) {
            $student_output_text_main .= strtoupper($gradeable->getName())." GRADE".$nl;
            $student_output_text_main .= "----------------------------------------------------------------------" . $nl;
            $name_and_emails = array();
            foreach($gradeable->getComponents() as $component){
                $name_and_emails[] = "{$component->getGrader()->getDisplayedFirstName()} {$component->getGrader()->getLastName()} <{$component->getGrader()->getEmail()}>".$nl;
            }
            $name_and_emails = implode(",", $name_and_emails);

            $student_output_text_main .= "Graded by : " . $name_and_emails;

            // Calculate late days for this gradeable
            $late_days = $ldu->getGradeable($student_id, $g_id);
            // TODO: add functionality to choose who regrade requests will be sent to
            $student_output_text_main .= "Any regrade requests are due within 7 days of posting to: ".$name_and_emails.$nl;
            if($gradeable->getDaysLate() > 0) {
                $student_output_text_main .= "This submission was submitted ".$gradeable->getDaysLate()." day(s) after the due date.".$nl;
            }
            if($late_days['extensions'] > 0) {
                $student_output_text_main .= "You have a ".$late_days['extensions']." day extension on this assignment.".$nl;
            }
            if(substr($late_days['status'], 0, 3) == 'Bad') {
                $student_output_text_main .= "NOTE: THIS ASSIGNMENT WILL BE RECORDED AS ZERO".$nl;
                $student_output_text_main .= "  Contact your TA or instructor if you believe this is an error".$nl.$nl;
            }
            if($late_days['late_days_charged'] > 0) {
                $student_output_text_main .= "Number of late days used for this homework: " . $late_days['late_days_charged'] . $nl;
            }
            $student_output_text_main .= "Total late days used this semester: " . $late_days['total_late_used'] . " (up to and including this assignment)" . $nl;
            $student_output_text_main .= "Late days remaining for the semester: " . $late_days['remaining_days'] . " (as of the due date of this homework)" . $nl;
            
            $student_output_text_main .= "----------------------------------------------------------------------" . $nl;

            if($gradeable->validateVersions()) {
                // uses active version...
                $active_version = $gradeable->getActiveVersion();
                $submit_file = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "results", $g_id, $student_id, $active_version, "grade.txt");
                $auto_grading_awarded = 0;
                $auto_grading_max_score = 0;
                if(!file_exists($submit_file)) {
                    $student_output_text .= $nl.$nl."NO AUTO-GRADE RECORD FOUND (contact the instructor if you did submit this assignment)".$nl.$nl;
                }
                else {
                    $auto_grading_awarded = $gradeable->getGradedAutograderPoints();
                    $auto_grading_max_score = $gradeable->getTotalAutograderNonExtraCreditPoints();
                    $student_output_text .= "AUTO-GRADING TOTAL [ " . $auto_grading_awarded . " / " . $auto_grading_max_score . " ]" . $nl;
                    $gradefilecontents = file_get_contents($submit_file);
                    $student_output_text .= "submission version #" . $active_version .$nl;
                    $student_output_text .= $nl.$gradefilecontents.$nl;
                }
                foreach($gradeable->getComponents() as $component) {
                    $student_output_text .= $component->getTitle() . "[" . $component->getScore() . "/" . $component->getMaxValue() . "] (Graded by {$component->getGrader()->getId()})".$nl;
                    if($component->getStudentComment() != "") {
                        $student_output_text .= "Rubric: " . $component->getStudentComment() . $nl;
                    }
                    if($component->getComment() != "") {
                        $student_output_text .= "TA NOTE: " . $component->getComment() . $nl;
                    }
                    $student_output_text .= $nl;
                    
                    $student_grade += $component->getScore();
                    if(!$component->getIsExtraCredit() && $component->getMaxValue() > 0) {
                        $rubric_total += $component->getMaxValue();
                        $ta_max_score += $component->getMaxValue();
                    }
                }
                $student_output_text .= "TA GRADING TOTAL [ " . $student_grade . " / " . $ta_max_score . " ]". $nl;
                $student_output_text .= "----------------------------------------------------------------------" . $nl;
                $rubric_total += $auto_grading_max_score;
                $student_grade += $auto_grading_awarded;
                
                $student_final_grade = max(0,$student_grade);
                $student_output_last = strtoupper($gradeable->getName()) . " GRADE [ " . $student_final_grade . " / " . $rubric_total . " ]" . $nl;
                $student_output_last .= $nl;
                $student_output_last .= "OVERALL NOTE FROM TA: " . ($gradeable->getOverallComment() != "" ? $gradeable->getOverallComment() . $nl : "No Note") . $nl;
                $student_output_last .= "----------------------------------------------------------------------" . $nl;
            }
            else {
                $student_output_text_main .= "NOTE: THIS ASSIGNMENT WILL BE RECORDED AS ZERO".$nl;
                $student_output_last = "[ THERE ARE GRADING VERSION CONFLICTS WITH THIS ASSIGNMENT. PLEASE CONTACT YOUR INSTRUCTOR OR TA TO RESOLVE THE ISSUE]".$nl;
            }

            $student_final_output = $student_output_text_main . $student_output_text. $student_output_last;
        }
        else {
            $student_final_output = "[ TA HAS NOT GRADED ASSIGNMENT, CHECK BACK LATER ]";
        }

        $dir = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "reports", $g_id);
        FileUtils::createDir($dir);

        $save_filename = FileUtils::joinPaths($dir, $student_output_filename);
        if(file_put_contents($save_filename, $student_final_output) === false) {
            // Need to change failure status, unsure how yet
            print "failed to write {$save_filename}\n";
        }
    }
    
    public function generateAllReports() {
        $students = $this->core->getQueries()->getAllUsers();
        $stu_ids = array_map(function($stu) {return $stu->getId();}, $students);
        $gradeables = $this->core->getQueries()->getGradeables(null, $stu_ids, "registration_section", "u.user_id", 0);
        $graders = $this->core->getQueries()->getAllGraders();
        $ldu = new LateDaysCalculation($this->core);
        foreach($gradeables as $gradeable) {
            $this->generateReport($gradeable, $ldu);
        }
    }
    
    public function generateSingleReport($student_id, $gradeable_id) {
        $gradeables = $this->core->getQueries()->getGradeables($gradeable_id, $student_id, "registration_section", "u.user_id", 0);
        $graders = $this->core->getQueries()->getAllGraders();
        $ldu = new LateDaysCalculation($this->core);
        foreach($gradeables as $gradeable) {
            $this->generateReport($gradeable, $ldu);
        }
    }
    
    public function generateAllReportsForGradeable($g_id) {
        $students = $this->core->getQueries()->getAllUsers();
        $stu_ids = array_map(function($stu) {return $stu->getId();}, $students);
        $gradeables = $this->core->getQueries()->getGradeables($g_id, $stu_ids, "registration_section", "u.user_id", 0);
        $graders = $this->core->getQueries()->getAllGraders();
        $ldu = new LateDaysCalculation($this-core);
        foreach($gradeables as $gradeable) {
            $this->generateReport($gradeable, $ldu);
        }
    }
    
    public function generateAllReportsForStudent($stu_id) {
        $gradeables = $this->core->getQueries()->getGradeables(null, $stu_id, "registration_section", "u.user_id", 0);
        $graders = $this->core->getQueries()->getAllGraders();
        $ldu = new LateDaysCalculation($this->core);
        foreach($gradeables as $gradeable) {
            $this->generateReport($gradeable, $ldu);
        }
    }
}

