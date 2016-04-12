<?php

namespace app\controllers\submission;

use app\controllers\IController;
use app\libraries\Output;

class Homework implements IController {
    public function run() {
        switch($_REQUEST['action']) {
            case 'display':
                break;
            case 'upload':
                break;
            case 'update':
                break;
            default:
                Output::render(array('submission', 'Global'), 'invalidPage', $_REQUEST['page']);
        }
    }
}