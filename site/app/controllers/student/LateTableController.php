<?php

namespace app\controllers\student;


use app\controllers\AbstractController;
use app\libraries\Core;

class LateTableController extends AbstractController {
    public function run() {
        $grade_path = $this->core->getConfig()->getCoursePath()."/reports/summary_html/"
            .$this->core->getUser()->getId()."_summary.html";

        $grade_file = null;
        if (file_exists($grade_path)) {
            $grade_file = file_get_contents($grade_path);
        }

        $this->core->getOutput()->renderOutput(array('submission', 'RainbowGrades'), 'showGrades', $grade_file);
    }
}