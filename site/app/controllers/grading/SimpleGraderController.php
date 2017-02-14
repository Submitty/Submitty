<?php

namespace app\controllers\grading;


use app\controllers\AbstractController;

class SimpleGraderController extends AbstractController  {
    public function run() {
        switch ($_REQUEST['action']) {
            case 'display':
                $this->display_form();
                break;
            case 'save':
                break;
            default:
                break;
        }
    }

    public function display_form() {
        $g_id = $_REQUEST['g_id'];
        $gradeable = $this->core->getQueries()->getGradeableById($g_id);
    }
}