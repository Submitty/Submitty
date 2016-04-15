<?php

namespace app\controllers;

use app\controllers\admin\AssignmentsController;
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

        $_REQUEST['page'] = (isset($_REQUEST['page'])) ? strtolower($_REQUEST['page']) : "";
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
            default:
                Output::showError("Invalid page request for controller");
                break;
        }
        $controller->run();
    }
}