<?php

namespace models;

use \lib\Database;
use \lib\ExceptionHandler;
use \lib\FileUtils;
use \lib\ServerException;
use \lib\Utils;

/**
 * Class ElectronicGradeable
 *
 * This contains all the logic for loading a electronic gradeable for grading
 * for a given student rcs and an existing gradeable ID. The class
 * gets the necessary files for the gradeable (submission, svn, and
 * result directories all scanned), calculates whether the assignment
 * is late, not submitted, etc. on an assignment as a whole and per
 * per part.
 *
 *
 * @package app\models
 */
class ElectronicGradeable
{

    /**
     * Array containing information about student from students table
     * @var array
     */
    public $student;

    /**
     * Array containing information about gradeable details joined with number of parts as well as
     * grade details
     * @var array
     */
    public $eg_details;

    /**
     * Array containing information about all questions and grades for each question
     * @var array
     */
    public $questions;

    /**
     * @var string
     */
    public $student_id;

    /**
     * @var null|int
     */
    public $g_id;

    public $autograding_points = 0;

    /**
     * Do we have a grade for this student on this gradeable in the database?
     * @var bool
     */
    public $has_grade = false;

    /**
     * Late days used, it's the max of late days used for the assignment without surpassing the max for any
     * given part. So on an assignment with two parts, one submitted 1 day late, and one submitted 20 days late,
     * if the second part is too late (late_days_max_surpassed == true), then $late_days = 1.
     * @var int
     */
    public $days_late = 0;

    /**
     * @var int
     */
    public $days_late_used = 0;

    /**
     * Number of days of exception that's given to the student for this particular gradeable to be applied to
     * all parts
     * @var int
     */
    public $late_days_exception = 0;

    /**
     * gradeable data id
     **/
    public $gd_id;

    //TODO Change status codes
    /**
     * This is a status for the assignment as a whole (potentially excluding some parts that are 0). We just leave it
     * at 0/1 (and not 2) as we only care whether or not graders can completely skip this homework or not (as well
     * as when generating grade reports, we can skip assignments with status 0).
     * 0 - Not accepted
     * 1 - Submitted and either late or on-time
     * @var int
     */
    public $status = 0;

    /**
     * Was the assignment submitted (1) or not (0)?
     * @var int
     */
    public $submitted = 0;

    /**
     * This is the assignment the student selected as active at the time of grading. If a gradeable has
     * been graded and then the active assignment changed, if the gradeable is gone back to, it'll use
     * the original active assignment from when it was first graded, not the new active assignment.
     * @var array
     */
    public $active_assignment;

    /**
     * This is set to be the number of submissions a student has attempted at a electronic gradeable though
     * this is not necessarily the assignment the student has selected as active
     * @var array
     */
    public $max_assignment;

    /**
     * @var array
     */
    public $total_submissions = array();

    /**
     * @var array
     */
    public $submission_details = array();

    /**
     * @var array
     */
    public $results_details = array();

    /**
     * @var array
     */
    public $config_details = array();

    /**
     * This contains all possible files for a electronic gradeable which include files from submission, results, and svn,
     * all while retaining file/directory structure
     * @var array
     */
    public $eg_files = array();

    /**
     * @var array
     */
    public $questions_count = array();

    /**
     * @var float
     * The maximum score for autograding excluding extra credit
     **/
    public $autograding_max = 0;

    /**
     * @var array
     */
    private $submission_ids = array();

    /** @var bool */
    public $graded = false;

    /** @var string */
    public $original_grader;

    /**
     * @param null|string $student_id
     * @param null|int $g_id
     *
     * @throws \InvalidArgumentException|\RuntimeException|ServerException
     */
    function __construct($student_id = null, $g_id = null)
    {
        try {
            if ($student_id === null || $g_id == null) {
                throw new \InvalidArgumentException("Invalid instantiation of ElectronicGradeable
                class using student_id: {$student_id} and g_id: {$g_id}");
            }

            $this->student_id = $student_id;
            $this->g_id = $g_id;
            $this->setEGDetails();
            $this->setStudentDetails();
            $this->setEGSubmissionDetails();
            $this->setEGResults();
            $this->setQuestionTotals();

            Utils::stripStringFromArray(__SUBMISSION_SERVER__, $this->eg_files);
        } catch (\Exception $ex) {
            ExceptionHandler::throwException("Gradeable", $ex);
        }
    }

    /**
     * Get the student details from the database using given student_id
     *
     * @throws \InvalidArgumentException
     */

    private function setStudentDetails()
    {
        if (!isset($this->student)) {

            //GETS THE ALLOWED LATE DAYS FOR A STUDENT AS OF THE SUBMISSION DATE
            Database::query("
                SELECT s.*, COALESCE(ld.allowed_late_days,0) as student_allowed_lates
                FROM users as s
                LEFT JOIN (
                    SELECT allowed_late_days
                    FROM late_days
                    WHERE since_timestamp <= ? and user_id=?
                    ORDER BY since_timestamp DESC
                ) as ld on 1=1
                WHERE s.user_id=? LIMIT 1", array($this->eg_details['eg_submission_due_date'], $this->student_id, $this->student_id));
            $this->student = Database::row();
            if ($this->student == array()) {
                throw new \InvalidArgumentException("Could not find student '{$this->student_id}'");
            }

            if ($this->student['student_allowed_lates'] < __DEFAULT_TOTAL_LATE_DAYS__) {
                $this->student['student_allowed_lates'] = __DEFAULT_TOTAL_LATE_DAYS__;
            }

            // late days used for the semester as of submission date
            $params = array($this->student_id, $this->eg_details['eg_submission_due_date']);
            Database::query("
                SELECT
                  eg.g_id
                  , eg.eg_late_days as assignment_allowed
                  , greatest(0, ceil(extract(EPOCH FROM(egd.submission_time - eg.eg_submission_due_date))/86400):: integer) as days_late
                  , eg.eg_submission_due_date
                  , egd.submission_time
                  , coalesce(lde.late_day_exceptions, 0) as extensions
                  , g.g_title
                  , coalesce(egv.active_version, -1) as active_version
                FROM
                  electronic_gradeable eg
                  , electronic_gradeable_version egv
                  , electronic_gradeable_data egd FULL OUTER JOIN late_day_exceptions lde ON lde.user_id = egd.user_id AND lde.g_id = egd.g_id
                  , gradeable g  
                  , late_days l
                WHERE
                  eg.g_id = egv.g_id
                  AND egv.g_id = egd.g_id
                  AND egv.user_id = egd.user_id
                  AND eg.g_id = g.g_id
                  AND egv.active_version = egd.g_version
                  AND l.user_id = egv.user_id
                  AND egv.user_id = ?
                  AND eg.eg_submission_due_date <=?
                ORDER BY
                  eg.eg_submission_due_date  
                ", $params);

            $this->student['used_late_days'] = Database::rows();

            $params = array($this->student_id, $this->eg_details['eg_submission_due_date']);
            Database::query("
                SELECT
                  *
                FROM
                  late_days l
                WHERE
                  l.user_id = ?
                  AND l.since_timestamp <= ?
                ORDER BY
                  l.since_timestamp
                ;
            ", $params);

            $this->student['earned_late_days'] = Database::rows();
        }
    }

    /**
     * Get the electronic gradeable and previous grade (if available) for the given g_id
     *
     * @throws \InvalidArgumentException
     */
    private function setEGDetails()
    {
        //CHECK IF THERE IS A GRADEABLE FOR THIS STUDENT

        $eg_details_query = "
SELECT g_title, gd_overall_comment, g_grade_start_date, eg.* FROM electronic_gradeable AS eg 
    INNER JOIN gradeable AS g ON eg.g_id = g.g_id
    INNER JOIN gradeable_data AS gd ON gd.g_id=g.g_id 
        WHERE gd_user_id=? AND g.g_id=?";

        Database::query($eg_details_query, array($this->student_id, $this->g_id));

        $this->eg_details = Database::row();

        if (empty($this->eg_details)) {
            //get the active version
            $assignment_settings = __SUBMISSION_SERVER__ . "/submissions/" . $this->g_id . "/" . $this->student_id . "/user_assignment_settings.json";
            if (!file_exists($assignment_settings)) {
                $active_version = -1;
            } else {
                $assignment_settings_contents = file_get_contents($assignment_settings);
                $results = json_decode($assignment_settings_contents, true);
                $active_version = $results['active_version'];
            }
            ///TODO UPDATE THE STATUS
            $params = array($this->g_id, $this->student_id, User::$user_id, '', 1, 0, $active_version);
            Database::query("INSERT INTO gradeable_data(g_id,gd_user_id,gd_grader_id,gd_overall_comment, gd_status,gd_late_days_used,gd_active_version, gd_user_viewed_date ) VALUES(?,?,?,?,?,?,?, NULL)", $params);
            $this->gd_id = \lib\Database::getLastInsertId('gradeable_data_gd_id_seq');

            Database::query($eg_details_query, array($this->student_id, $this->g_id));

            $this->eg_details = Database::row();
        } else {
            $params = array($this->student_id, $this->g_id);
            Database::query("SELECT gd_id FROM gradeable as g INNER JOIN gradeable_data AS gd ON g.g_id=gd.g_id WHERE gd_user_id=? AND g.g_id =?", $params);
            $this->gd_id = Database::row()['gd_id'];
        }

        $this->submission_ids[1] = $this->eg_details['g_id'];

        $params = array($this->eg_details['g_id'], $this->gd_id);

        //GET ALL questions and scores ASSOCIATED WITH A GRADEABLE
        Database::query("
SELECT gc.*, gcd.*, gd.gd_grader_id,
    case when gcd_score is null then FALSE else TRUE end as is_graded,
    case when gcd_score is null then 0 else gcd_score end
FROM gradeable_component AS gc 
    INNER JOIN gradeable AS g ON gc.g_id=g.g_id 
    INNER JOIN gradeable_data AS gd ON g.g_id=gd.g_id
    LEFT JOIN gradeable_component_data AS gcd ON gd.gd_id=gcd.gd_id AND gc.gc_id=gcd.gc_id
WHERE g.g_id=? 
AND gd.gd_id=?
ORDER BY gc_order ASC
        ", $params);
        $this->questions = Database::rows();

        $this->graded = false;
        foreach ($this->questions as $question) {
            if ($question['is_graded']) {
                $this->graded = true;
                $this->original_grader = $question['gd_grader_id'];
            }
        }

        $total = 0;
        $build_file = __SUBMISSION_SERVER__ . "/config/build/build_" . $this->g_id . ".json";
        if (file_exists($build_file)) {
            $build_file_contents = file_get_contents($build_file);
            $results = json_decode($build_file_contents, true);
            if (isset($results['testcases']) && count($results['testcases']) > 0) {
                foreach ($results['testcases'] as $testcase) {
                    $testcase_value = floatval($testcase['points']);
                    if ($testcase_value > 0 && !$testcase['extra_credit']) {
                        $total += $testcase_value;
                    }
                }
            }
        }
        $this->autograding_max = $total;
    }

    /**
     * Get the files associated with the assignment from the submission directory
     */
    private function setEGSubmissionDetails()
    {

        foreach ($this->submission_ids as $submission_id) {
            $submission_directory = implode("/", array(__SUBMISSION_SERVER__, "submissions", $submission_id, $this->student_id));
            if (!file_exists($submission_directory)) {
                continue;
            }

            $objects = scandir($submission_directory);
            $objects = array_filter($objects, function ($element) use ($submission_directory) {
                return is_dir($submission_directory . "/" . $element) && !in_array($element, array('.', '..'));
            });
            sort($objects);
            if (count($objects) > 0) {
                $this->max_assignment = end($objects);
            } else {
                continue;
            }

            $details = array();
            $this->submitted = 1;

            if (!$this->has_grade || !isset($this->active_assignment) || $this->active_assignment <= 0) {
                if (file_exists(implode("/", array($submission_directory, "user_assignment_settings.json")))) {
                    $settings = json_decode(file_get_contents(implode("/", array($submission_directory, "user_assignment_settings.json"))), true);
                    $this->active_assignment = $settings['active_version'];
                    // If the active_assignment is -1 in the file, then the submission was "cancelled"
                    if ($settings['active_version'] == 0) {
                        continue;
                    }
                } else {
                    $this->active_assignment = $this->max_assignment;
                }
            }
            ////////////////////////////////////////////////////////////////////

            $submission_directory = $submission_directory . "/" . $this->active_assignment;

            if (!file_exists($submission_directory) || !is_dir($submission_directory)) {
                throw new \InvalidArgumentException("Couldn't find directory for active assignment {$this->active_assignment}");
            }

            $details['submission_directory'] = $submission_directory;
            $details['svn_directory'] = implode("/", array(__SUBMISSION_SERVER__, "checkout", $submission_id, $this->student_id, $this->active_assignment));
            $this->submission_details = $details;
            $this->eg_files = array_merge($this->eg_files, FileUtils::getAllFiles($details['submission_directory']));
            $this->eg_files = array_merge($this->eg_files, FileUtils::getAllFiles($details['svn_directory']));

            $this->config_details = json_decode(
                removeTrailingCommas(file_get_contents(implode("/", array(__SUBMISSION_SERVER__, "config",
                    "build", "build_" . $submission_id . ".json")))), true);
        }
    }

    /**
     * Get result files associated with the assignment
     */
    private function setEGResults()
    {
        foreach ($this->submission_ids as $submission_id) {
            $submission_directory = implode("/", array(__SUBMISSION_SERVER__, "submissions", $submission_id,
                $this->student_id, $this->active_assignment));
            $result_directory = implode("/", array(__SUBMISSION_SERVER__, "results",
                $submission_id, $this->student_id, $this->active_assignment));

            if (!file_exists($result_directory) || !is_dir($result_directory)) {
                continue;
            }

            $results_file = $result_directory . "/results.json";
            $timestamp = file_get_contents($submission_directory . "/.submit.timestamp");
            if (!file_exists($results_file)) {
                $details = array();
            } else {
                $details = json_decode(file_get_contents($results_file), true);
                // TODO: Convert this to using DateTime and DateTimeInterval objects
                $date_submission = strtotime($timestamp);
                $details['submission_time'] = $timestamp;
                $date_due = strtotime($this->eg_details["eg_submission_due_date"]) + 1;
                $days_late = round((($date_submission - $date_due) / (60 * 60 * 24)) + .5, 0);
                $this->days_late = ($days_late < 0) ? 0 : $days_late;
            }

            $details['directory'] = $result_directory;

            // We can lazy load the actual results till we need them (such as the diffs, etc.)
            $this->results_details = $details;

            $skip_files = array();
            if (isset($this->results_details['testcases'])) {
                foreach ($this->results_details['testcases'] as $testcase) {
                    if (isset($testcase['diffs'])) {
                        foreach ($testcase['diffs'] as $diff) {
                            foreach (array("expected_file", "actual_file", "diff_id") as $file) {
                                if (isset($diff[$file])) {
                                    $skip_files[] = $diff[$file] . ($file == 'diff_id' ? '.json' : '');
                                }
                            }
                        }
                    }
                    //FIXME this won't work for extra credit auto-grading
                    if (isset($testcase['points_awarded'])) {
                        $this->autograding_points += $testcase['points_awarded'];
                    }
                }
            }
            $this->eg_files = array_merge($this->eg_files, FileUtils::getAllFiles($result_directory, array(), $skip_files));
        }
    }

    /**
     * Calculate the current grade for each question. This is either the saved value in the DB (for a regrade),
     * a 0 if the electronic gradeable wasn't submitted or the ZERO_RUBRIC_GRADES flag is set,
     * otherwise set the question to its potential full value.
     */
    private function setQuestionTotals()
    {
        $total = 0;
        for ($i = 0; $i < count($this->questions); ++$i) {
            $question = &$this->questions[$i];
            if (!isset($question['gcd_score'])) {
                if ($this->status == 0) {
                    $question['gcd_score'] = 0;
                } else if (__USE_AUTOGRADER__ && $question['gc_order'] < 2) {
                    $question['gcd_score'] = 0;

                    if ($question['gc_order'] == 1) {
                        $question['gcd_score'] += $this->results_details['non_extra_credit_points_awarded'];
                    } else {
                        $question['gcd_score'] += $this->results_details['extra_credit_points_awarded'];
                    }
                } else if (__ZERO_RUBRIC_GRADES__) {
                    $question['gcd_score'] = 0;
                } else {
                    $question['gcd_score'] = $question['gc_max_value'];
                }
            }

            if (!$question['gc_is_extra_credit'] && $question['gc_max_value'] > 0) {
                $total += $question['gc_max_value'];
            }
        }
        $this->eg_details['eg_total'] = $total;
    }
}
