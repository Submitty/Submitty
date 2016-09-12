<?php

namespace app\controllers\student;


use app\controllers\IController;
use app\libraries\Core;

class RainbowGradesController implements IController {
    private $core;

    public function __construct(Core $core) {
        $this->core = $core;
    }

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