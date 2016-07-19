<?php

namespace app\controllers;

use app\controllers\admin\GradeablesController;
use app\controllers\admin\ConfigurationController;
use app\controllers\admin\UsersController;
use app\libraries\Core;
use app\libraries\Output;
use app\models\User;

class AdminController implements IController {
    /**
     * @var Core
     */
    private $core;

    public function __construct(Core $core) {
        $this->core = $core;
    }

    public function run() {
        if (!$this->core->getUser()->accessAdmin()) {
            $this->core->getOutput()->showError("This account cannot access admin pages");
        }

        $controller = null;
        switch ($_REQUEST['page']) {
            case 'gradeables':
                $controller = new GradeablesController($this->core);
                break;
            case 'labs':
                break;
            case 'tests':
                break;
            case 'users':
                $controller = new UsersController($this->core);
                break;
            case 'configuration':
                $controller = new ConfigurationController($this->core);
                break;
            default:
                $this->core->getOutput()->showError("Invalid page request for controller ".get_class($this));
                break;
        }
        $controller->run();
    }
}