<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\Utils;

/**
 * Class ClassJson
 *
 * Model representing the class.json that exists for any given course. Additionally, it contains a model
 * for the current assignment that is being looked at by the client (either latest assignment or one
 * specified by the client). This model is then used to build the Submission page for students.
 */
class ClassJson {
    /**
     * @var Core
     */
    private $core;

    private $allowed_assignments = null;
    private $assignment = null;

    public function __construct(Core $core, $assignment = null) {
        $this->core = $core;
        $this->class = Utils::loadJsonFile($this->core->getConfig()->getCoursePath()."config/class.json");
        $this->getAssignments();
        if ($assignment === null || !array_key_exists($assignment, $this->allowed_assignments)) {
            $array = array_slice($this->allowed_assignments, -1);
            $assignment = array_pop($array);
        }
        else {
            $assignment = $this->allowed_assignments[$assignment];
        }

        $this->assignment = new Assignment($this->core, $assignment);
    }

    public function getAllAssignments() {
        return $this->class['assignments'];
    }

    public function getAssignments() {
        if ($this->allowed_assignments === null) {
            $this->allowed_assignments = array();
            foreach ($this->getAllAssignments() as $assignment) {
                if ($this->core->getUser()->accessAdmin() || $assignment['released'] === true) {
                    $this->allowed_assignments[] = $assignment;
                }
            }
        }
        return $this->allowed_assignments;
    }

    public function getCurrentAssignment() {
        return $this->assignment;
    }
}