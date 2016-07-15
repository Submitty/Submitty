<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\Utils;

/**
 * Class Assignment
 *
 * Model of the current assignment being looked at for submission by the student. The information is a combination
 * of the info contained in the class.json file for the assignment as well as information solely in the assignment's
 * json file (as well as the relevant json files for the student's given attempt at the assignment.
 */
class Assignment {

    /**
     * @var Core
     */
    private $core;

    private $details;

    public function __construct(Core $core, $assignment) {
        $this->core = $core;
        $this->details = $assignment;

        $this->details = array_merge($this->details, Utils::loadJsonFile($this->core->getConfig()->getCoursePath()."/config/".
                                                                         $assignment['assignment_id'].
                                                                         "_assignment_config.json"));

        /*
        TODO:
        1) Get number of submission attempts by current user ($this->core->getUser())
        2) Get version of the assignment (either through $_REQUEST['assignment_version'] or last attempt)
        3) Get ta grading details (if available)
        */
    }
}