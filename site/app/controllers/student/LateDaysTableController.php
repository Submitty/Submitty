<?php

namespace app\controllers\student;


use app\controllers\AbstractController;
use app\libraries\Core;

class LateDaysTableController extends AbstractController {
    public function run() {
        $this->core->getOutput()->renderOutput(array('LateDaysTable'), 'showLateTable', $this->core->getUser()->getId());
    }
}