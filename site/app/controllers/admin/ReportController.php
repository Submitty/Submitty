<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\Core;
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
            if(!isset($results['header_model'][$g_id])) {
                $results['header_model'][$g_id] = $g_id.": ".($gradeable->getTotalAutograderNonExtraCreditPoints() + $gradeable->getTotalTANonExtraCreditPoints());
            }

            $autograding_score = $gradeable->getGradedAutograderPoints();
            $ta_grading_score = $gradeable->getGradedTAPoints();
            
            $late_days = $ldu->getGradeable($gradeable->getUser()->getId(), $gradeable->getId());
            if(substr($late_days['status'], 0, 3) == 'Bad' || !$gradeable->validateVersions()) {
                $results[$student_id][$g_id] = 0;
            }
            else{
                $results[$student_id][$g_id] = $autograding_score + $ta_grading_score;
            }
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

