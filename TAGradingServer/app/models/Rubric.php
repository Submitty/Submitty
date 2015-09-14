<?php

namespace app\models;

use \lib\Database;
use \lib\ExceptionHandler;
use \lib\FileUtils;
use \lib\ServerException;

/**
 * Class Rubric
 * @package app\models
 */
class Rubric {
    
    /**
     * Array containing information about student from students table
     * @var array
     */
    public $student;
    
    /**
     * Array containing information about rubric details joined with number of parts as well as 
     * grade details
     * @var array
     */
    public $rubric_details;
    
    /**
     * Array containing information about all questions and grades for each question
     * @var array
     */
    public $questions;
    
    /**
     * @var string
     */
    public $student_rcs;

    /**
     * @var null|int
     */
    public $rubric_id;

    /**
     * Do we have a grade for this student on this rubric in the database?
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
     * Number of days of exception that's given to the student for this particular rubric to be applied to
     * all parts
     * @var int
     */
    public $late_days_exception = 0;
    
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
     * @var int
     */
    public $submitted = 0;
    
    /**
     * @var array
     */
    public $active_assignment = array();
    
    /**
     * @var array
     */
    public $max_assignment = array();
    
    /**
     * This is status for each individual part of the assignment, could potentially be 0 for a part, while
     * status for assignment as a whole could be '1' (meaning some parts were accepted)
     * 0 - Not accepted (too late/not submitted)
     * 1 - Submitted and on-time
     * 2 - Submitted and late
     * @var array
     */
    public $parts_status = array();
    
    /**
     * @var array
     */
    public $parts_submitted = array();
    
    /**
     * @var array
     */
    public $parts_days_late = array();
    
    /**
     * @var array
     */
    public $parts_days_late_used = array();
    
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
     * This contains all possible files for a rubric which include files from submission, results, and svn,
     * all while retaining file/directory structure
     * @var array
     */
    public $rubric_files = array();
    
    /**
     * @var int
     */
    public $rubric_parts = 0;
    
    public $questions_count = array();
    
    private $submission_ids = array();

    /**
     * @param null|string $student_rcs
     * @param null|int $rubric_id
     *
     * @throws \InvalidArgumentException|\RuntimeException|ServerException
     */
    function __construct($student_rcs=null, $rubric_id=null) {
        try {
            if ($student_rcs === null || $rubric_id == null) {
                throw new \InvalidArgumentException("Invalid instation of Homework 
                class using student_rcs: {$student_rcs} and rubric_id: 
                {$rubric_id}");
            }

            $this->student_rcs = $student_rcs;
            $this->rubric_details_id = $rubric_id;
    
            $this->setRubricDetails();
            $this->setStudentDetails();
            $this->setRubricSubmissionDetails();
            $this->setRubricResults();
            $this->calculateStatus();
            $this->setQuestionTotals();
            for ($i = 1; $i <= $this->rubric_parts; $i++) {
                sort($this->rubric_files[$i]);
            }
        }
        catch (\Exception $ex) {
            ExceptionHandler::throwException("Homework", $ex);
        }
    }

    /**
     * Get the student details from the database using given student_rcs
     * 
     * @throws \InvalidArgumentException
     */
    private function setStudentDetails() {
        if (!isset($this->student)) {
            Database::query("
SELECT s.*, ld.allowed_lates as student_allowed_lates
FROM students as s
LEFT JOIN (
    SELECT *
    FROM late_days
    WHERE since_timestamp <= ?
) as ld on ld.student_rcs = s.student_rcs
WHERE s.student_rcs=?", array($this->rubric_details['rubric_due_date'], $this->student_rcs));
            $this->student = Database::row();
            if ($this->student == array()) {
                throw new \InvalidArgumentException("Could not find student '{$this->student_rcs}'");
            }
    
            $params = array($this->student_rcs, $this->rubric_details['rubric_id']);
            Database::query("SELECT * FROM late_day_exceptions WHERE ex_student_rcs=? AND ex_rubric_id=?", $params);
            $row = Database::row();
            $this->late_days_exception = (isset($row['ex_late_days'])) ? $row['ex_late_days'] : 0;
    
            $params = array($this->student_rcs, $this->rubric_details['rubric_due_date']);
            Database::query("
SELECT (SUM(g.grade_days_late) - SUM(s.ex_late_days)) as used_late_days
FROM grades as g 
    LEFT JOIN 
    (
        SELECT * FROM late_day_exceptions
    ) as s on s.ex_rubric_id = g.rubric_id and s.ex_student_rcs=g.student_rcs
    LEFT JOIN
    (
        SELECT rubric_id, rubric_due_date FROM rubrics
    ) as r on r.rubric_id = g.rubric_id
WHERE g.student_rcs=? AND g.grade_status=1 AND r.rubric_due_date<?", $params);
            $row = Database::row();
            $this->student['used_late_days'] = isset($row['used_late_days']) ? $row['used_late_days'] : 0;
        }
    }

    /**
     * Get the rubric and previous grade (if available) for the given rubric_number
     * 
     * @throws \InvalidArgumentException
     */
    private function setRubricDetails() {
        // Must have rubric after grade in select list else g.rubric_id will 
        // override r.rubric_id which is bad if g.rubric_id is null (grade doesn't exist)
        Database::query("
SELECT g.*, r.*,b.rubric_parts
FROM rubrics as r 
    LEFT JOIN (
        SELECT g.*, u.* 
        FROM grades as g 
            LEFT JOIN (SELECT * FROM users) as u ON g.grade_user_id=u.user_id 
        WHERE student_rcs=?) as g ON r.rubric_id=g.rubric_id
    LEFT JOIN (
        SELECT count(DISTINCT question_part_number) as rubric_parts, rubric_id 
        FROM questions 
        WHERE question_part_number > 0 
        GROUP BY rubric_id) as b ON b.rubric_id=r.rubric_id
WHERE r.rubric_id=?", array($this->student_rcs, $this->rubric_details_id));
        
        if (count(Database::rows()) == 0) {
            throw new \InvalidArgumentException("Could not find rubric '{$this->rubric_details_id}'");
        }
        $this->rubric_details = Database::row();
    
        if ($this->rubric_details['rubric_parts_sep']) {
            $this->rubric_parts = $this->rubric_details['rubric_parts'];
            $submission_ids = explode(",", $this->rubric_details['rubric_parts_submission_id']);
            if (count($submission_ids) != $this->rubric_parts) {
                throw new \RuntimeException("You must have submission_ids defined for all parts");
            }
            for ($i = 1; $i <= $this->rubric_parts; $i++) {
                $this->submission_ids[$i] = $this->rubric_details['rubric_submission_id'].$submission_ids[$i-1];
            }
        }
        else {
            $this->rubric_parts = 1;
            $this->submission_ids[1] = $this->rubric_details['rubric_submission_id'];
        }
        
        $fields = array('parts_submitted', 'parts_status');
        if (!isset($this->rubric_details['grade_id']) || intval($this->rubric_details['grade_id']) == 0) {
            $this->rubric_details['grade_id'] = -1;
            for ($i = 1; $i <= $this->rubric_details['rubric_parts']; $i++) {
                foreach($fields as $k) {
                    $this->$k[$i] = 0;
                }
                $this->active_assignment[$i] = -1;
                $this->rubric_files[$i] = array();
            }
        }
        else {
            $this->has_grade = true;
            foreach($fields as $k) {
                if (isset($this->rubric_details['grade_'.$k])) {
                    $$k = explode(",",$this->rubric_details['grade_'.$k]);
                }
                else {
                    $$k = array();
                }
            }
            $active_assignment = explode(",", $this->rubric_details['grade_active_assignment']);
            for ($i = 1; $i <= $this->rubric_details['rubric_parts']; $i++) {
                $j = $i - 1;
                foreach($fields as $k) {
                    $this->$k[$i] = (isset($$k[$j]) && intval($$k[$j]) == 1) ? 1 : 0;
                }
                $this->active_assignment[$i] = intval($active_assignment[$j]);
                $this->rubric_files[$i] = array();
            }
        }
        
        $params = array($this->rubric_details['grade_id'], $this->rubric_details['rubric_id']);
        Database::query("
SELECT q.*, gq.*, q.question_id as question_id 
FROM questions as q 
    LEFT JOIN ( 
        SELECT a.*, b.comments 
        FROM grades_questions as a 
            LEFT JOIN (
                SELECT question_id, count(*) as count, array_agg(grade_question_comment) as comments 
                FROM grades_questions GROUP BY question_id ORDER BY count) as b 
            ON a.question_id = b.question_id WHERE grade_id=?) as gq 
    ON q.question_id = gq.question_id 
WHERE rubric_id=? 
ORDER BY question_part_number, question_number", $params);
        $this->questions = Database::rows();

        Database::query("
SELECT question_part_number, COUNT(*) as count 
FROM questions 
WHERE rubric_id=? 
GROUP BY question_part_number 
ORDER BY question_part_number", array($this->rubric_details['rubric_id']));
        foreach (Database::rows() as $row) {
            $this->questions_count[$row['question_part_number']] = $row['count'];
        }
    }

    /**
     * Get the files associated with the assignment from the submission directory
     */
    private function setRubricSubmissionDetails() {
        $part = 1;
        foreach($this->submission_ids as $submission_id) {
            $submission_directory = implode("/", array(__SUBMISSION_SERVER__, "submissions", $submission_id, $this->student_rcs));
            if (!file_exists($submission_directory)) {
                print "<div style='z-index: 999999;'>".$submission_directory."</div>";
                continue;
            }
            
            $details = array();
            $this->submitted = 1;
            $this->parts_submitted[$part] = 1;
    
            $objects = scandir($submission_directory);
            sort($objects);
            $this->max_assignment[$part] = $objects[count($objects) - 2];
            
            if (isset($_GET["active_assignment_{$part}"]) && 1 == 2) {
                $_GET["active_assignment_{$part}"] = intval($_GET["active_assignment_{$part}"]);
                if ($_GET["active_assignment_{$part}"] <= 0) {
                    if (!$this->rubric_details['rubric_parts_sep']) {
                        throw new \InvalidArgumentException("Cannot have zero or negative active assignment for assignment");
                    }
                    else {
                        throw new \InvalidArgumentException("Cannot have a negative active assignment for part {$part}");
                    }
                }
                $this->active_assignment[$part] = $_GET["active_assignment_{$part}"];
            }
            else if (!$this->has_grade || !isset($this->active_assignment[$part]) || $this->active_assignment[$part] == 0) {
                if (file_exists(implode("/", array($submission_directory, "user_assignment_settings.json")))) {
                    $settings = json_decode(file_get_contents(implode("/", array($submission_directory, "user_assignment_settings.json"))), true);
                    $this->active_assignment[$part] = $settings['active_assignment'];
                }
                else {
                    $this->active_assignment[$part] = $this->max_assignment[$part];
                }
            }

            $submission_directory = $submission_directory . "/" . $this->active_assignment[$part];

            if (!file_exists($submission_directory) || !is_dir($submission_directory)) {
                if (!$this->rubric_details['rubric_parts_sep']) {
                    throw new \InvalidArgumentException("Couldn't find directory for active assignment {$this->active_assignment[$part]}");
                }
                else {
                    throw new \InvalidArgumentException("Couldn't find directory for active assignment {$this->active_assignment[$part]} for part {$part}");
                }
            }
            
            $details['submission_directory'] = $submission_directory;
            $details['svn_directory'] = implode("/", array(__SUBMISSION_SERVER__, "checkout", $submission_id, $this->student_rcs, $this->active_assignment[$part]));
            $this->submission_details[$part] = $details;
            $allowed_file_extensions = explode(",", __ALLOWED_FILE_EXTENSIONS__);
            
            $this->rubric_files[$part] = array_merge($this->rubric_files[$part], FileUtils::getAllFiles($details['submission_directory'], $allowed_file_extensions));
            $this->rubric_files[$part] = array_merge($this->rubric_files[$part], FileUtils::getAllFiles($details['svn_directory']));
    
            $this->config_details[$part] = json_decode(
                removeTrailingCommas(file_get_contents(implode("/",array(__SUBMISSION_SERVER__,"config",
                    $submission_id."_assignment_config.json")))), true);
            $part++;
        }
    }

    /**
     * Get result files associated with the assignment
     */
    private function setRubricResults() {
        $part = 1;
    
        foreach($this->submission_ids as $submission_id) {
            
            $result_directory = implode("/", array(__SUBMISSION_SERVER__, "results",
                $submission_id, $this->student_rcs, $this->active_assignment[$part]));

            if (!file_exists($result_directory) || !is_dir($result_directory)) {
                continue;
            }
            
            $submission_details = $result_directory."/submission.json";
            if (!file_exists($submission_details)) {
                $details = array();
            }
            else {
                $details = json_decode(file_get_contents($submission_details), true);
                // TODO: Convert this to using DateTime and DateTimeInterval objects
                $date_submission = strtotime($details['submission_time']);
                $date_due = strtotime($this->rubric_details["rubric_due_date"]) + 1 + __SUBMISSION_GRACE_PERIOD_SECONDS__;
                $late_days = round((($date_submission - $date_due) / (60 * 60 * 24)) + .5, 0);
                $late_days = ($late_days < 0) ? 0 : $late_days;
                $this->parts_days_late[$part] = $late_days;
                $this->parts_days_late_used[$part] = $this->parts_days_late[$part] - $this->late_days_exception;
            }
            
            $details['directory'] = $result_directory;

            // We can lazy load the actual results till we need them (such as the diffs, etc.)
            $this->results_details[$part] = $details;
            
            $this->rubric_files[$part] = array_merge($this->rubric_files[$part], FileUtils::getAllFiles($result_directory));
            
            $part++;
        }
    }

    /**
     * 
     */
    private function calculateStatus() {
        if (!$this->submitted) {
            return;
        }
        for ($i = 1; $i <= $this->rubric_parts; $i++) {
            if (!$this->parts_submitted[$i]) {
                continue;
            }
            
            if ($this->rubric_details['rubric_late_days'] >= 0 &&
                $this->parts_days_late_used[$i] > $this->rubric_details['rubric_late_days']) {
                $this->parts_status[$i] = 0;
            }
            else if ($this->student['student_allowed_lates'] >= 0 && 
                $this->student['used_late_days'] + $this->parts_days_late_used[$i] > $this->student['student_allowed_lates']) {
                $this->parts_status[$i] = 0;
            }
            else if ($this->parts_days_late_used[$i] > 0) {
                $this->parts_status[$i] = 2;
            }
            else {
                $this->parts_status[$i] = 1;
            }
            
            if ($this->parts_status[$i] > 0) {
                $this->status = 1;
                $this->days_late = max($this->days_late, $this->parts_days_late[$i]);
            }
        }
        
    }

    /**
     * 
     */
    private function setQuestionTotals() {
        $total = 0;
        
        foreach ($this->questions as $i => $question) {
            if (!isset($this->questions[$i]['grade_question_score'])) {
                if (__USE_AUTOGRADER__ && $this->questions[$i]['question_part_number'] == 0) {
    
                    foreach ($this->results_details as $part => $details) {
                        if ($this->questions[$i]['question_number'] == 1) {
                            $this->questions[$i]['grade_question_score'] = $this->results_details[$part]['non_extra_credit_points_awarded'];
                        }
                        else {
                            $this->questions[$i]['grade_question_score'] = $this->results_details[$part]['extra_credit_points_awarded'];
                        }
                    }
                }
                else if (!$this->status[$this->questions[$i]['question_part_number']] || __ZERO_RUBRIC_GRADES__) {
                    $this->questions[$i]['grade_question_score'] = 0;
                }
                else {
                    $this->questions[$i]['grade_question_score'] = $this->questions[$i]['question_total'];
                }
            }
            
            if (!$this->questions[$i]['question_extra_credit']) {
                $total += $this->questions[$i]['question_total'];
            }
        }
        $this->rubric_details['rubric_total'] = $total;
        //var_dump($this->questions);
    }
    
    public function dumpStuff() {
        var_dump($this);
    }
}
