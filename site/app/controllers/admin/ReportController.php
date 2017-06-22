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
                $this->showCSVReport();
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
    
    public function showCSVReport() {
        
    }
    
    public function generateGradeSummaries() {
        if (!$this->core->getUser()->accessAdmin()) {
            $this->core->getOutput()->showError("Non Admin User attempting to access admin page");
        }
        else {
            $grade_summary = new GradeSummary($this->core);
            $grade_summary->generateAllSummaries();
            $_SESSION['messages']['success'][] = "Successfully Generated GradeSummaries";
            $this->core->getOutput()->renderOutput(array('admin', 'Report'), 'showReportUpdates');
        }
    }
    
    public function generateHWReports() {
        if (!$this->core->getUser()->accessAdmin()) {
            $this->core->getOutput()->showError("Non Admin User attempting to access admin page");
        }
        else {
            $hw_report = new HWReport($this->core);
            $hw_report->generateAllReports();
            $_SESSION['messages']['success'][] = "Successfully Generated HWReports";
            $this->core->getOutput()->renderOutput(array('admin', 'Report'), 'showReportUpdates');
        }
    }
}
?>
