<?php

namespace app\controllers;

use app\libraries\Core;
use app\libraries\Output;
/*
use app\report\HWReportView;
use app\report\CSVReportView;
use app\report\GradeSummaryView;
*/
class ReportController extends AbstractController {
    public function run() {
        switch($_REQUEST['action']) {
            case 'hw':
                $this->showHWReport();
                break;
            case 'csv':
                $this->showCSVReport();
                break;
            case 'summary':
                $this->showGradeSummary();
                break;
            default:
                $this->core->getOutput()->showError("Invalid action request for controller ".get_class($this));
                break;
        }
    }
    
    public function showHWReport() {
        $this->core->getOutput()->renderOutput(array('admin', 'HWReport'), 'showHWReport');
    }
    
    public function showCSVReport() {
        
    }
    
    public function showGradeSummary() {
        
    }
}
?>