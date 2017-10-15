<?php

namespace app\controllers;

use app\controllers\admin\ReportController;
use app\controllers\admin\GradeableController;
use app\controllers\admin\GradeablesController;
use app\controllers\admin\AdminGradeableController;
use app\controllers\admin\ConfigurationController;
use app\controllers\admin\UsersController;
use app\controllers\admin\LateController;
use app\controllers\admin\PlagiarismController;
use app\libraries\Core;
use app\libraries\Output;
use app\models\User;

class AdminController extends AbstractController {
    public function run() {
        if (!$this->core->getUser()->accessAdmin()) {
            $this->core->getOutput()->showError("This account cannot access admin pages");
        }

        $this->core->getOutput()->addBreadcrumb('Admin');
        $controller = null;
        switch ($_REQUEST['page']) {
            case 'users':
                $controller = new UsersController($this->core);
                break;
            case 'configuration':
                $this->core->getOutput()->addBreadcrumb('Course Settings');
                $controller = new ConfigurationController($this->core);
                break;
            case 'gradeable':
                $controller = new GradeableController($this->core);
                break;
            case 'late':
                $controller = new LateController($this->core);
                break;
            case 'admin_gradeable':
                $controller = new AdminGradeableController($this->core);
                break;
            case 'reports':
                $this->core->getOutput()->addBreadcrumb('Report');
                $controller = new ReportController($this->core);
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
