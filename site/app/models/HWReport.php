<?php
namespace app\models;

use app\models\LateDaysCalculation;
use app\libraries\Core;
use app\libraries\FileUtils; 
use app\libraries\GradeableType;

class HWReport extends AbstractModel {
    /*var Core */
    protected $core;
    
    public function __construct(Core $main_core) {
        $this->core = $main_core;
    }
    
    private function generateReport($gradeable, $ldu) {

    	// don't generate reports for things that aren't electronic gradeables with TA grading
        if (!($gradeable->getType() === GradeableType::ELECTRONIC_FILE and $gradeable->useTAGrading())) {
          return;
        }

    	// Make sure we have a good directory
        if (!is_dir(implode(DIRECTORY_SEPARATOR, array($this->core->getConfig()->getCoursePath(), "reports")))) {
            mkdir(implode(DIRECTORY_SEPARATOR, array($this->core->getConfig()->getCoursePath(), "reports")));
        }
        $nl = "\n";
        $TEMP_EMAIL = $this->core->getConfig()->getCourseEmail();
        $write_output = True;
        $g_id = $gradeable->getId();
        $rubric_total = 0;
        $ta_max_score = 0;
        // Gather student info, set output filename, reset output

        $student_final_output = "";
	$student_output_total = "";
	$student_output_header = "";
	$student_output_auto = "";
	$student_output_ta_header = "";
	$student_output_ta = "";
	$student_output_separator = "----------------------------------------------------------------------------------------------" . $nl;

        $student_grade = 0;
        $grade_comment = "";
        
        $student_id = $gradeable->isTeamAssignment() ? $gradeable->getTeam()->getId() : $gradeable->getUser()->getId();
        $student_output_filename = $student_id.".txt";
        $late_days_used_overall = 0;
        
        // Only generate full report when the TA has graded the work, may want to change
        if($gradeable->beenTAgraded()) {
            $names = array();
            $name_and_emails = array();
            $peer_component_count = 0;
            foreach($gradeable->getComponents() as $component){
                if(is_array($component)) {
                    $peer_component_count++;
                    foreach($component as $cmpt) {
                        if(!$cmpt->getGrader() == null) {
                            $names[] = "Peers";
                        }
                    }
                    continue;
                }
                else if($component->getGrader() === null) {
                    //nothing happens, this is the case when a ta has not graded a component
                } 
                else if($component->getGrader()->accessFullGrading()) {
                    $names[] = "{$component->getGrader()->getDisplayedFirstName()} {$component->getGrader()->getLastName()}";
                    $name_and_emails[] = "{$component->getGrader()->getDisplayedFirstName()} {$component->getGrader()->getLastName()} <{$component->getGrader()->getEmail()}>";
                } else {
                    $name_and_emails[] = $TEMP_EMAIL;
                }
                
            }

            $names = array_unique($names);
            $names = implode(", ", $names);
            $name_and_emails = array_unique($name_and_emails);
            $name_and_emails = implode(", ", $name_and_emails);

            $student_output_header .= "Graded by : " . $names;

            // Calculate late days for this gradeable
            $late_days = $ldu->getGradeable($gradeable->getUser()->getId(), $g_id);
            // TODO: add functionality to choose who regrade requests will be sent to
            $student_output_header .= $nl;
            $student_output_header .= "Any regrade requests are due within 7 days of posting to: ".$name_and_emails.$nl;
            $student_output_header .= $nl.$student_output_separator;
            $student_output_header .= "Submission Deadline and Late Day Information".$nl;
            $student_output_header .= "  Total late days available as of this assignment's due date: ".$late_days['allowed_per_term'].$nl;
            $prev_late_days_used = $late_days['total_late_used']-$late_days['late_days_charged'];
            $student_output_header .= "  Late days used on previous assignments: ".$prev_late_days_used.$nl;
            $student_output_header .= "  Maximum late days allowed on this assignment: ".$late_days['allowed_per_assignment'].$nl;
            if ($late_days['late_days_used'] > 0) {
              $student_output_header .= "  Assignment was submitted ".$late_days['late_days_used']." day(s) after the due date.".$nl;
            }
            if ($late_days['extensions'] > 0) {
              $student_output_header .= "  You have a ".$late_days['extensions']." day extension on this assignment.".$nl;
            }
            $student_output_header .= "  Submission Status: ".$late_days['status'].$nl;
            if ($late_days['late_days_used'] > 0 || $late_days['late_days_charged'] > 0) {
              $student_output_header .= "  Number of late days charged for this homework: " . $late_days['late_days_charged'] . $nl;
            }
            $student_output_header .= "  Total late days used this semester: " . $late_days['total_late_used'] . " (up to and including this assignment)" . $nl;
            $student_output_header .= "  Late days remaining for the semester: " . $late_days['remaining_days'] . " (as of the due date of this assignment)" . $nl;
            if(substr($late_days['status'], 0, 3) == 'Bad') {
              $student_output_header .= $nl."NOTE: DUE TO LATE SUBMISSION, THIS ASSIGNMENT WILL BE RECORDED AS ZERO".$nl;
              $student_output_header .= "  Contact your TA or instructor if you believe this is an error".$nl.$nl;
            }

            if($gradeable->validateVersions()) {
                $active_version = $gradeable->getActiveVersion();
                $submit_file = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "results", $g_id, $student_id, $active_version, "grade.txt");
                $auto_grading_awarded = 0;
                $auto_grading_max_score = 0;
                if(!file_exists($submit_file)) {
                    $student_output_auto .= $nl.$nl."NO AUTO-GRADE RECORD FOUND (contact the instructor if you did submit this assignment)".$nl.$nl;
                }
                else {
                    $auto_grading_awarded = $gradeable->getGradedAutograderPoints();
                    $auto_grading_max_score = $gradeable->getTotalAutograderNonExtraCreditPoints();
                    $student_output_auto .= "AUTO-GRADING SUBTOTAL [ " . $auto_grading_awarded . " / " . $auto_grading_max_score . " ]" . $nl;
                    $gradefilecontents = file_get_contents($submit_file);
                    $student_output_auto .= "submission version #" . $active_version .$nl;
                    $student_output_auto .= $nl.$gradefilecontents.$nl;
                }

                foreach($gradeable->getComponents() as $component) {

                    // it's already a component...
                    // if($component->getOrder() == -1) {
                    //     $grading_units = $gradeable->getPeerGradeSet() * $peer_component_count;
                    //     $completed_components = $this->core->getQueries()->getNumGradedPeerComponents($gradeable->getId(), $this->core->getUser()->getId());
                    //     $score = $gradeable->roundToPointPrecision($completed_components * $component->getMaxValue() / $grading_units);
                    //     $student_output_ta .= "Points for Grading Completion: [". $score. " / ".$component->getMaxValue()."]".$nl;
                    //     continue;
                    // }

                    if(is_array($component)) {
                        $peer_score = 0;
                        $temp_notes = "Peer graded question." . $nl;
                        $stu_count = 0;
                        $peer_score = 0;
                        foreach($component as $peer_comp){
                            $stu_count++;
                            $peer_score += $peer_comp->getGradedTAPoints();
                            $temp_notes .= "Student " . $stu_count . "'s score: " . $peer_comp->getGradedTAPoints() . $nl . $peer_comp->getGradedTAComments($nl, true) . $nl;
                        }
                        $temp_score = $peer_score/$stu_count;
                        $title = $component[0]->getTitle();
                        $max_value = $component[0]->getMaxValue();
                        $student_comment = $component[0]->getStudentComment();
                    }
                    else {
                        $title = $component->getTitle();
                        $max_value = $component->getMaxValue();
                        $student_comment = $component->getStudentComment();
                        $temp_score = $component->getGradedTAPoints();
                        $temp_notes = $component->getGradedTAComments($nl, true) . $nl;
                    }
                    
                    $student_output_ta .= $title . " [ " . $temp_score . " / " . $max_value . " ] ";
                    if (!is_array($component) && $component->getGrader() !== null && $component->getGrader()->accessFullGrading()) {
                        $student_output_ta .= "(Graded by {$component->getGrader()->getId()})".$nl;
                    } else {
                        $student_output_ta .= $nl;
                    }
                    
                    if($student_comment != "") {
                        $student_output_ta .= "Rubric: " . $student_comment . $nl;
                    }

                    $student_output_ta .= $temp_notes;

                    $student_output_ta .= $nl;
                    
                    $student_grade += $temp_score;
                    $rubric_total += $max_value;
                    $ta_max_score += $max_value;
                }
                $student_output_ta_header .= "TA GRADING SUBTOTAL [ " . $student_grade . " / " . $ta_max_score . " ]". $nl . $nl;
                $student_output_ta_header .= "OVERALL NOTE FROM TA: " . ($gradeable->getOverallComment() != "" ? $gradeable->getOverallComment() . $nl : "No Note") . $nl . $nl;

                $rubric_total += $auto_grading_max_score;
                $student_grade += $auto_grading_awarded;
                
                $student_final_grade = max(0,$student_grade);

                if(substr($late_days['status'], 0, 3) == 'Bad') {
                  $student_final_grade = 0;
                }

                $student_output_total .= strtoupper($gradeable->getName()) . " GRADE [ " . $student_final_grade . " / " . $rubric_total . " ]" . $nl;

            }
            else {
                $student_output_header .= "NOTE: THIS ASSIGNMENT WILL BE RECORDED AS ZERO".$nl;
                $student_output_total = "[ THERE ARE GRADING VERSION CONFLICTS WITH THIS ASSIGNMENT. PLEASE CONTACT YOUR INSTRUCTOR OR TA TO RESOLVE THE ISSUE]".$nl;
            }

            $student_final_output .= $student_output_separator . $student_output_total;
            $student_final_output .= $student_output_separator . $student_output_header;
            $student_final_output .= $student_output_separator . $student_output_auto;
            $student_final_output .= $student_output_separator . $student_output_ta_header . $student_output_ta;
            $student_final_output .= $student_output_separator;
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
        $size_of_stu_id_chunks = 75; //ceil(count($stu_ids) / 2);
        $stu_chunks = array_chunk($stu_ids, $size_of_stu_id_chunks);

        foreach ($stu_chunks as $stu_chunk) {
            $gradeables = $this->core->getQueries()->getGradeables(null, $stu_chunk, "registration_section");
            $ldu = new LateDaysCalculation($this->core, $stu_chunk);
            foreach($gradeables as $gradeable) {
                $this->generateReport($gradeable, $ldu);
            }
        }
    }
    
    public function generateSingleReport($student_id, $gradeable_id) {
        $gradeables = $this->core->getQueries()->getGradeables($gradeable_id, $student_id, "registration_section");
        $ldu = new LateDaysCalculation($this->core, $student_id);
        foreach($gradeables as $gradeable) {
            $this->generateReport($gradeable, $ldu);
        }
    }
    
    public function generateAllReportsForGradeable($g_id) {
        $students = $this->core->getQueries()->getAllUsers();
        $stu_ids = array_map(function($stu) {return $stu->getId();}, $students);
        $size_of_stu_id_chunks = 150; //ceil(count($stu_ids) / 2);
        $stu_chunks = array_chunk($stu_ids, $size_of_stu_id_chunks);

        foreach ($stu_chunks as $stu_chunk) {
            $gradeables = $this->core->getQueries()->getGradeables($g_id, $stu_chunk, "registration_section");
            $ldu = new LateDaysCalculation($this->core, $stu_chunk);
            foreach($gradeables as $gradeable) {
                $this->generateReport($gradeable, $ldu);
            }
        }
    }
    
    public function generateAllReportsForStudent($stu_id) {
        $gradeables = $this->core->getQueries()->getGradeables(null, $stu_id, "registration_section", "u.user_id", 0);
        $ldu = new LateDaysCalculation($this->core, $stu_id);
        foreach($gradeables as $gradeable) {
            $this->generateReport($gradeable, $ldu);
        }
    }
}

