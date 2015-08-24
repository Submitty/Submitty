<?php

namespace app\models;

use \lib\Database;
use \lib\ExceptionHandler;
use \lib\ServerException;

/**
 * Class Rubric
 * @package lib
 */
class Rubric {
    
    /**
     * @var array
     */
    public $student;
    
    /**
     * @var array
     */
    public $rubric_details;
    
    /**
     * @var array
     */
    public $questions;

    /**
     * @var array
     */
    public $questions_count;
    
    /**
     * @var string
     */
    public $student_rcs;

    /**
     * @var null|int
     */
    public $rubric_id;

    /**
     * @var bool
     */
    public $has_grade = false;

    /**
     * @var int
     */
    public $late_days = 0;

    /**
     * @var int
     */
    public $late_days_exception = 0;

    /**
     * @var int
     */
    public $used_late_days = 0;

    /**
     * @var bool
     */
    public $late_days_max_surpassed = false;
    
    /**
     * @var bool
     */
    public $submitted = false;

    /**
     * @var int
     */
    public $status = 0;

    /**
     * @var array
     */
    public $active_assignment = array();

    /**
     * @var array
     */
    public $submitted_details = array();
    
    /**
     * @var array
     */
    public $submitted_files = array();

    /**
     * @var array
     */
    public $results_details = array();

    /**
     * @var array
     */
    public $results_files = array();

    /**
     * @param null|string $student_rcs
     * @param null|int $rubric_id
     *
     * @throws \InvalidArgumentException
     * @throws ServerException
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

            $this->setStudentDetails();
            $this->setRubricDetails();
            $this->setRubricSubmissionFiles();
            $this->setRubricResults();
            $this->calculateLateDays();
            $this->calculateStatus();
            $this->setQuestionTotals();
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
            Database::query("SELECT * FROM students WHERE student_rcs=?", array($this->student_rcs));
            $this->student = Database::row();
            if ($this->student == array()) {
                throw new \InvalidArgumentException("Could not find student '{$this->student_rcs}'");
            }
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
SELECT g.*, r.* 
FROM rubrics as r 
    LEFT JOIN (
        SELECT g.*, u.* 
        FROM grades as g 
            LEFT JOIN (SELECT * FROM users) as u ON g.grade_user_id=u.user_id 
        WHERE student_rcs=?) as g ON r.rubric_id=g.rubric_id 
WHERE r.rubric_id=?", array($this->student_rcs, $this->rubric_details_id));
        
        if (count(Database::rows()) == 0) {
            throw new \InvalidArgumentException("Could not find rubric '{$this->rubric_details_id}'");
        }
        $this->rubric_details = Database::row();

        if (!isset($this->rubric_details['grade_id']) || intval($this->rubric_details['grade_id']) == 0) {
            $this->rubric_details['grade_id'] = -1;
        }
        else {
            $this->has_grade = true;
        }
        $params = array($this->rubric_details['grade_id'], $this->rubric_details['rubric_id']);
        Database::query("
SELECT q.*, gq.* 
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
        
        Database::query("SELECT question_part_number, COUNT(*) as count FROM questions WHERE rubric_id=? GROUP BY question_part_number ORDER BY question_part_number", array($this->rubric_details['rubric_id']));
        foreach (Database::rows() as $row) {
            $this->questions_count[$row['question_part_number']] = $row['count'];
        }
    }

    /**
     * Get the files associated with the assignment from the submission directory
     */
    private function setRubricSubmissionFiles() {
        $rubric_dir = $this->rubric_details['rubric_name'];
        $part_number = 1;
        if($this->rubric_details['rubric_parts_sep']) {
            $rubric = $rubric_dir . "_part" . $part_number;
        }
        else {
            $rubric = $rubric_dir;
        }
        
        //print implode("/", array(__SUBMISSION_SERVER__, "submissions", $rubric));
        while (file_exists(implode("/", array(__SUBMISSION_SERVER__, "submissions", $rubric)))) {
            $details = array();
            $submission_directory = implode("/", array(__SUBMISSION_SERVER__, "submissions", $rubric, $this->student_rcs));
            
            if (!file_exists($submission_directory)) {
                // no submission for this student, so should fail?
                // TODO: decide best way to fail
                return;
            }
            $this->submitted = true;

            // TODO: Finish this and hook up the database to have a 'rubric_active_assignment' column
            // #
            if (!$this->has_grade || 1 == 1) {
                if (file_exists(implode("/", array($submission_directory, "user_assignment_settings.json")))) {
                    $settings = json_decode(file_get_contents(implode("/", array($submission_directory, "user_assignment_settings.json"))), true);
                    $active_assignment = $settings['active_assignment'];
                } else {
                    $objects = scandir($submission_directory);
                    sort($objects);
                    $active_assignment = $objects[count($objects) - 2];
                }
            }
            else {
                // TODO: have a method to override this for manual regrades
                $active_assignment = $this->rubric_details['rubric_active_assignment'];
            }

            $submission_directory = $submission_directory . "/" . $active_assignment;
            $this->active_assignment = $active_assignment;

            if (!file_exists($submission_directory) || !is_dir($submission_directory)) {
                // doesn't exist or not a valid directory
                return;
            }

            $files = array();
            $allowed_file_extensions = explode(",", __ALLOWED_FILE_EXTENSIONS__);
            
            if ($handle = opendir($submission_directory)) {
                while (false !== ($entry = readdir($handle))) {
                    if ($entry != "." && $entry != "..") {
                        $info = pathinfo($submission_directory . "/" . $entry);
                        if (in_array($info['extension'], $allowed_file_extensions)) {
                            $files[] = $entry;
                        }
                    }
                }
            }
            $details['directory'] = $submission_directory;
            
            $this->submitted_details[$part_number] = $details;
            $this->submitted_files[$part_number] = $files;
            $rubric = $rubric_dir . "_part" . (++$part_number);
        }
    }

    /**
     * Get result files associated with the assignment
     */
    private function setRubricResults() {
        $rubric_dir = $this->rubric_details['rubric_name'];
        $part_number = 1;
        if($this->rubric_details['rubric_parts_sep']) {
            $rubric = $rubric_dir . "_part" . $part_number;
        }
        else {
            $rubric = $rubric_dir;
        }
        
        while (file_exists(implode("/", array(__SUBMISSION_SERVER__, "results", $rubric)))) {
            
            $result_directory = implode("/", array(__SUBMISSION_SERVER__, "results",
                $rubric, $this->student_rcs, $this->active_assignment));

            if (!file_exists($result_directory) || !is_dir($result_directory)) {
                // no results for this student so should we fail?
                // TODO: decide best way to fail
                return;
            }
            
            $files = array();
            
            $submission_details = $result_directory."/submission.json";
            if (!file_exists($submission_details)) {
                $details = array();
            }
            else {
                $details = json_decode(file_get_contents($submission_details), true);
                $date_submission = strtotime($details['submission_time']);
                $date_due = strtotime($this->rubric_details["rubric_due_date"]) + 1 + __SUBMISSION_GRACE_PERIOD_SECONDS__;
                $late_days = round((($date_submission - $date_due) / (60 * 60 * 24)) + .5, 0);
                $late_days = ($late_days < 0) ? 0 : $late_days;
                $details['late_days'] = $late_days;
                $this->late_days = max($this->late_days, $late_days);
                
                // we need to load any files that start with . as all other files are related to
                // individual test cases
                foreach (scandir($result_directory, SCANDIR_SORT_ASCENDING) as $file) {
                    if ($file == "." || $file == "..") {
                        continue;
                    }
                    else if(substr($file,0,1) == ".") {
                        $files[] = $file;
                    }
                    else {
                        break;
                    }
                }
            }
            
            $details['directory'] = $result_directory;

            // We can lazy load the actual results till we need them (such as the diffs, etc.)
            $this->results_details[$part_number] = $details;
            $this->results_files[$part_number] = $files;
            
            $rubric = $rubric_dir . "_part" . (++$part_number);
        }
    }

    /**
     * 
     */
    private function calculateLateDays() {
        $params = array($this->student_rcs, $this->rubric_details['rubric_id']);
        Database::query("SELECT * FROM late_day_exceptions WHERE ex_student_rcs=? AND ex_rubric_id=?", $params);
        $row = Database::row();
        $this->late_days_exception = (isset($row['ex_late_days'])) ? $row['ex_late_days'] : 0;
        
        $params = array($this->student_rcs, $this->rubric_details['rubric_number']);
        Database::query(
"SELECT (SUM(g.grade_days_late) - SUM(s.ex_late_days)) as used_late_days
FROM grades as g 
LEFT JOIN 
(
SELECT * FROM late_day_exceptions
) as s 
on s.ex_rubric_id = g.rubric_id and s.ex_student_rcs=g.student_rcs
WHERE g.student_rcs=? AND g.status=1 AND g.rubric_id<?", $params);
        $row = Database::row();
        $this->student['used_late_days'] = isset($row['used_late_days']) ? $row['used_late_days'] : 0;
    }

    /**
     * 
     */
    private function calculateStatus() {
        $this->status = 0;
        if (!$this->submitted) {
            return;
        }
        $this->used_late_days = $this->late_days - $this->late_days_exception;
        if ($this->rubric_details['rubric_late_days'] >= 0 &&
            $this->rubric_details['rubric_late_days'] < $this->used_late_days) {
            $this->late_days_max_surpassed = true;
            return;
        }
        if ($this->student['student_allowed_lates'] >= 0 && 
            $this->student['used_late_days'] + $this->used_late_days > $this->student['student_allowed_lates']) {
            $this->late_days_max_surpassed = true;
            return;
        }
        
        $this->status = 1;
    }

    /**
     * 
     */
    private function setQuestionTotals() {
        $total = 0;
        for ($i = 0; $i < count($this->questions); $i++) {
            if (!isset($this->questions[$i]['grade_question_score'])) {
                if (!$this->status || __ZERO_RUBRIC_GRADES__) {
                    $this->questions[$i]['grade_question_score'] = 0;
                }
                else if (__USE_AUTOGRADER__ && $this->questions[$i]['question_part_number'] == 0) {
                    foreach ($this->rubric_details as $part => $details) {
                        $this->questions[$i]['grade_question_score'] += ($this->questions[$i]['question_number'] == 1) ?
                            $details['non_extra_credit_points_awareded'] : $details['extra_credit_points_awarded'];
                    }
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
    }
    
    public function dumpStuff() {
        var_dump($this);
    }
}
