<?php

namespace app\controllers;

use app\libraries\Output;
use app\models\Config;

class SubmissionController implements IController {
    public function run() {
        Output::render(array('submission', 'Global'), 'header', Config::$course_name);
        switch ($_REQUEST['page']) {
            case 'homework':
                $controller = new submission\Homework();
                $controller->run();
                break;
            default:
                print "what";
                Output::render('Error', 'invalidPage', $_REQUEST['page']);
        }
        Output::render(array('submission', 'Global'), 'footer');
    }
}