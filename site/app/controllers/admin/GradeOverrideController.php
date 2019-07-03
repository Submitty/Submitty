<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\DateUtils;
use app\libraries\FileUtils;

class GradeOverrideController extends AbstractController {
    public function run() {
        switch ($_REQUEST['action']){
            case 'view_overriden_grades':
                $this->viewOverridenGrades();
                $this->core->getOutput()->addBreadcrumb('Grades Override');
                break;
                
        }
    }

    public function viewOverridenGrades() {
        $this->core->getOutput()->renderOutput(array('admin','GradeOverride'), 'displayOverridenGrades');
    }
}