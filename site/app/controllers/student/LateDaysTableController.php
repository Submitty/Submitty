<?php

namespace app\controllers\student;


use app\controllers\AbstractController;
use app\libraries\Core;

class LateDaysTableController extends AbstractController {
    public function run() {
        $g_id = isset($_REQUEST["g_id"]) ? $_REQUEST["g_id"] : NULL;
        switch ($_REQUEST['action']) {
            case 'plugin_table':
                return $this->core->getOutput()->renderOutput(array('LateDaysTable'), 'showLateTable', $this->core->getUser()->getId(), $g_id, false);
                break;
            default:
                return $this->core->getOutput()->renderOutput(array('LateDaysTable'), 'showLateTable', $this->core->getUser()->getId(), $g_id, true);
                break;
        }
    }
}