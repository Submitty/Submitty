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
 * @method array     getError()
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
            $this->subjects[$row["subject"]] = $row["created"];
            if ($row["semester"] != null || $row["course"] != null) {
                $this->courses[$row["subject"]] = $row["semester"] . ' ' . $row["course"];
            }
            if ($row["sent"] != null) {
                $this->successes[$row["subject"]][] = $row;
            }
            elseif ($row["error"] != null) {
                $this->errors[$row["subject"]][] = $row;
            }
            else {
                $this->pending[$row["subject"]][] = $row;
            }
        }
    }
}
