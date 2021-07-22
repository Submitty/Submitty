<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\DateUtils;

/**
 * Class EmailStatusModel
 *
 * @method array     getSubjects()
 * @method array     getPending()
 * @method array     getSuccesses()
 * @method array     getErrors()
 * @method array     getCourses()
 */
class EmailStatusModel extends AbstractModel {
    // A map of all unique subjects of emails to the time created
    /** @prop-read Set */
    protected $subjects = [];
    // A map of email subjects to the rows that are still pending to send in the database
    /** @prop-read array */
    protected $pending = [];
    // A map of email subjects to the rows that successfully sent in the database
    /** @prop-read array */
    protected $successes = [];
    // A map of email subjects to the rows that resulted in an error in the database
    /** @prop-read array */
    protected $errors = [];
    // A map of email subjects to the semester and course as one string
    /** @prop-read array */
    protected $courses = [];

    public function __construct(Core $core, $data) {
        parent::__construct($core);
        foreach ($data as $row) {
            $key = $this->EmailToKey($row);
            if (!in_array($key, $this->subjects)) {
                $this->subjects[] = $key;
                $this->successes[$key] = [];
                $this->errors[$key] = [];
                $this->pending[$key] = [];
            }
            if ($row->getSemester() != null || $row->getCourse() != null) {
                $this->courses[$key] = $row->getSemester() . ' ' . $row->getCourse();
            }
            if ($row->getSent() != null) {
                $this->successes[$key][] = $row;
            }
            elseif ($row->getError() != null) {
                $this->errors[$key][] = $row;
            }
            else {
                $this->pending[$key][] = $row;
            }
        }
    }
    
    private function EmailToKey ($row) {
        return $row->getSubject() . ', ' . $row->getCreated()->format('Y-m-d H:i:s');
    }
}
