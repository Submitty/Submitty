<?php

namespace app\controllers;

use app\controllers\admin\ReportController;
use app\controllers\admin\GradeableController;
use app\controllers\admin\GradeablesController;
use app\controllers\admin\AdminGradeableController;
use app\controllers\admin\UsersController;
use app\controllers\admin\LateController;
use app\controllers\admin\PlagiarismController;
use app\controllers\admin\WrapperController;
use app\controllers\admin\EmailRoomSeatingController;
use app\libraries\Core;
use app\libraries\Output;
use app\models\User;

class AdminController extends AbstractController {
    public function run() {
        if (!$this->core->getUser()->accessAdmin()) {
            $this->core->getOutput()->showError("This account cannot access admin pages");
        }

        //$this->core->getOutput()->addBreadcrumb('Admin');
        $controller = null;
        switch ($_REQUEST['page']) {
            case 'users':
                $controller = new UsersController($this->core);
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
                $controller = new ReportController($this->core);
                break;
            case 'plagiarism':
                $controller = new PlagiarismController($this->core);
                break;
            case 'wrapper':
                $this->core->getOutput()->addBreadcrumb("Customize Website Theme");
                $controller = new WrapperController($this->core);
                break;
            case 'email_room_seating':
                $this->core->getOutput()->addBreadcrumb("Email Room Seating");
                $controller = new EmailRoomSeatingController($this->core);
                break;
            default:
                $this->core->getOutput()->showError("Invalid page request for controller ".get_class($this));
                break;
        }
        $controller->run();
    }
}
