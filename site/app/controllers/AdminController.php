<?php

namespace app\controllers;

use app\controllers\admin\AdminGradeableController;
use app\controllers\admin\GradeOverrideController;
use app\controllers\admin\PlagiarismController;

class AdminController extends AbstractController {
    public function run() {
        if (!$this->core->getUser()->accessAdmin()) {
            $this->core->getOutput()->showError("This account cannot access admin pages");
        }

        //$this->core->getOutput()->addBreadcrumb('Admin');
        $controller = null;
        switch ($_REQUEST['page']) {
            case 'grade_override':
                $controller = new GradeOverrideController($this->core);
                break;
            case 'admin_gradeable':
                $controller = new AdminGradeableController($this->core);
                break;
            case 'plagiarism':
                $controller = new PlagiarismController($this->core);
                break;
            default:
                $this->core->getOutput()->showError("Invalid page request for controller ".get_class($this));
                break;
        }
        $controller->run();
    }
}
