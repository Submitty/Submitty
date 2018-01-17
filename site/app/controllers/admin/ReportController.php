<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\Core;
use app\libraries\GradeableType;
use app\libraries\Output;
use app\models\HWReport;
use app\models\GradeSummary;
use app\models\LateDaysCalculation;

/*
use app\report\HWReportView;
use app\report\CSVReportView;
use app\report\GradeSummaryView;
*/
class ReportController extends AbstractController {
    public function run() {
        switch($_REQUEST['action']) {
            case 'reportpage':
                $this->showReportPage();
                break;
            case 'csv':
                $this->generateCSVReport();
                break;
            case 'summary':
                $this->generateGradeSummaries();
                break;
            case 'hwreport':
                $this->generateHWReports();
                break;
            default:
                $this->core->getOutput()->showError("Invalid action request for controller ".get_class($this));
                break;
        }
    }
    
    public function showReportPage() {
        $this->core->getOutput()->renderOutput(array('admin', 'Report'), 'showReportUpdates');
    }
    
    public function generateCSVReport() {
        $students = $this->core->getQueries()->getAllUsers();
        $student_ids = array_map(function($stu) {return $stu->getId();}, $students);
        $gradeables = $this->core->getQueries()->getGradeables(null, $student_ids);
        $results = array();
        $results['header_model'] = array('First' => 'First Name', 'Last'=> 'Last Name', 'reg_section' => 'Registration Section');
        $ldu = new LateDaysCalculation($this->core);
        foreach($gradeables as $gradeable) {
            $student_id = $gradeable->getUser()->getId();
            if(!isset($results[$student_id])) {
                $results[$student_id] = array('First'=>$gradeable->getUser()->getDisplayedFirstName(), 'Last' => $gradeable->getUser()->getLastName(), 'reg_section' => $gradeable->getUser()->getRegistrationSection());
            }
            $g_id = $gradeable->getId();
            $is_electronic_gradeable = ($gradeable->getType() == GradeableType::ELECTRONIC_FILE);
            $use_ta_grading = !$is_electronic_gradeable || $gradeable->useTAGrading();

            if(!isset($results['header_model'][$g_id])) {
              $max = 0;
              if ($is_electronic_gradeable) {
                $max = $max + $gradeable->getTotalAutograderNonExtraCreditPoints();
              }
              if ($use_ta_grading) {
                $max = $max + $gradeable->getTotalTANonExtraCreditPoints();
              }
              $results['header_model'][$g_id] = $g_id.": ".$max;
            }

            $total_score = 0;
            if ($is_electronic_gradeable) {
              $total_score = $total_score + $gradeable->getGradedAutograderPoints();
            }
            if ($use_ta_grading) {
              $total_score = $total_score + $gradeable->getGradedTAPoints();
            }
            
            $late_days = $ldu->getGradeable($gradeable->getUser()->getId(), $gradeable->getId());
            // if this assignment exceeds the allowed late day policy or
            // if the student has switched versions after the ta graded,
            // then they should receive an automatic zero for this gradeable
            if( $is_electronic_gradeable &&
                ( (array_key_exists('status',$late_days) && substr($late_days['status'], 0, 3) == 'Bad') ||
                  ($use_ta_grading && !$gradeable->validateVersions()))) {
              $total_score = 0;
            }

            $results[$student_id][$g_id] = $total_score;
        }
        
        $nl = "\n";
        $csv_output = "";
        $filename = $this->core->getConfig()->getCourse()."CSVReport.csv";
        foreach($results as $id => $student) {
            $student_line = array();
            if($id === 'header_model') {
                $student_line[] = "UserId";
            }
            else {
                $student_line[] = $id;
            }
            $student_line[] = $student['First'];
            $student_line[] = $student['Last'];
            $student_line[] = $student['reg_section'];
            foreach($results['header_model'] as $grade_id => $grade) {
                if($grade_id == 'First' || $grade_id == 'Last' || $grade_id == 'reg_section') {
                    continue;
                }
                $student_line[] = $student[$grade_id];
            }
            $csv_output .= implode(",",$student_line).$nl;
        }
        $this->core->getOutput()->renderFile($csv_output, $filename);
        return $csv_output;
    }
    
    public function generateGradeSummaries() {
        $grade_summary = new GradeSummary($this->core);
        $grade_summary->generateAllSummaries();
        $this->core->addSuccessMessage("Successfully Generated GradeSummaries");
        $this->core->getOutput()->renderOutput(array('admin', 'Report'), 'showReportUpdates');
    }
    
    public function generateHWReports() {
        $hw_report = new HWReport($this->core);
        $hw_report->generateAllReports();
        $this->core->addSuccessMessage("Successfully Generated HWReports");
        $this->core->getOutput()->renderOutput(array('admin', 'Report'), 'showReportUpdates');
    }
}

