<?php

namespace app\controllers;

use app\libraries\Core;
use app\libraries\Output;

class SubmissionController implements IController {
    /**
     * @var Core
     */
    private $core;

    public function __construct(Core $core) {
        $this->core = $core;
    }

    public function run() {
        $assignments = $this->core->getQueries()->getAllAssignments();

        Output::render(array('submission', 'Homework'), 'startContent');

        $controller = null;
        switch ($_REQUEST['page']) {
            case 'homework':
            default:
                $controller = new submission\HomeworkController($this->core, $assignments);
                break;
        }

        $controller->run();
        Output::render(array('submission', 'Homework'), 'endContent');
    }
}