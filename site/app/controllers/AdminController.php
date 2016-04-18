<?php

namespace app\controllers;

use app\controllers\admin\AssignmentsController;
use app\controllers\admin\ConfigurationController;
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
            Output::showError("This account cannot access admin pages");
        }

        $controller = null;
        switch ($_REQUEST['page']) {
            case 'assignments':
                $controller = new AssignmentsController($this->core);
                break;
            case 'labs':
                break;
            case 'tests':
                break;
            case 'users':
                break;
            case 'configuration':
                $controller = new ConfigurationController($this->core);
                break;
            default:
                Output::showError("Invalid page request for controller");
                break;
        }
        $controller->run();
    }
}