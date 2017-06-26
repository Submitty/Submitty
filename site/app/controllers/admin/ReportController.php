<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\Core;
use app\libraries\Output;
use app\models\HWReport;
use app\models\GradeSummary;
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
        foreach($gradeables as $gradeable) {
            $stu_id = $gradeable->getUser()->getId();
            if(!isset($results[$stu_id])) {
                $results[$stu_id] = array('First' => $gradeable->getUser()->getFirstName(), 'Last' => $gradeable->getUser()->getLastName(), 'reg_section' => $gradeable->getUser()->getRegistrationSection());
                foreach($results['header_model'] as $s_buckets => $assignments) {
                    $results[$stu_id][$s_buckets] = array();
                }
            }
            $g_id = $gradeable->getId();
            $syllabus_bucket = $gradeable->getSyllabusBucket();
            if(!isset($results['header_model'][$syllabus_bucket])) {
                $results['header_model'][$syllabus_bucket] = array($g_id => $g_id);
            }
            if(!isset($results[$stu_id][$syllabus_bucket])) {
                $results[$stu_id][$syllabus_bucket] = array($g_id => $gradeable->getGradedTAPoints());
            }
            else {
                $results[$stu_id][$syllabus_bucket][$g_id] = $gradeable->getGradedTAPoints();
            }
            if(!isset($results['header_model'][$syllabus_bucket][$g_id])) {
                $results['header_model'][$syllabus_bucket][$g_id] = $g_id;
            }
        }
        
        $csv_output = "";
        
        foreach($results as $id => $user_grades) {
            $row = array();
            if($id === 'header_model') {
                $row[] = 'UserId';
            }
            else {
                $row[] = $id;
            }
            
            $row[] = $results[$id]['First'];
            $row[] = $results[$id]['Last'];
            $row[] = $results[$id]['reg_section'];
            foreach($results[$id] as $s_bucket => $gradeables) {
                if($s_bucket == 'First' || $s_bucket == 'Last' || $s_bucket == 'reg_section') {
                    continue;
                }
                $row[] = $s_bucket;
                foreach($results[$id][$s_bucket] as $score) {
                    $row[] = $score;
                }
            }
            $student_row = implode(",", $row);
            $csv_output .= $student_row."\n";
        }
        header("Content-type: text/plain");
        header("Contenet-Disposition: attachment; filename=hwserver-report.csv");
        header("Content-Length: ".strlen($csv_output));
        
        echo $csv_output;
        $this->core->getOutput()->renderOutput(array('admin', 'Report'), 'showReportUpdates');
    }
    
    public function generateGradeSummaries() {
        $grade_summary = new GradeSummary($this->core);
        $grade_summary->generateAllSummaries();
        $_SESSION['messages']['success'][] = "Successfully Generated GradeSummaries";
        $this->core->getOutput()->renderOutput(array('admin', 'Report'), 'showReportUpdates');
    }
    
    public function generateHWReports() {
        $hw_report = new HWReport($this->core);
        $hw_report->generateAllReports();
        $_SESSION['messages']['success'][] = "Successfully Generated HWReports";
        $this->core->getOutput()->renderOutput(array('admin', 'Report'), 'showReportUpdates');
    }
}

