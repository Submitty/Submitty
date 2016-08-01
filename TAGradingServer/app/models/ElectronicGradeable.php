<?php

namespace app\models;

use \lib\Database;
use \lib\ExceptionHandler;
use \lib\FileUtils;
use \lib\ServerException;
use \lib\Utils;

//TODO replace RCS references

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
class ElectronicGradeable {

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
     gradeable data id
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
     * @var array
     */
    private $submission_ids = array();

    /**
     * @param null|string $student_id
     * @param null|int $rubric_id
     *
     * @throws \InvalidArgumentException|\RuntimeException|ServerException
     */
    function __construct($student_id=null, $g_id=null) {
        try {
            if ($student_id === null || $g_id == null) {
                throw new \InvalidArgumentException("Invalid instantiation of ElectronicGradeable
                class using student_id: {$student_id} and g_id:
                {$g_id}");
            }

            $this->student_id = $student_id;
            $this->g_id = $g_id;

            $this->setEGDetails();
            $this->setStudentDetails();
            $this->setEGSubmissionDetails();
            $this->setEGResults();
            $this->calculateStatus();
            $this->setQuestionTotals();

            Utils::stripStringFromArray(__SUBMISSION_SERVER__, $this->eg_files);
        }
        catch (\Exception $ex) {
            ExceptionHandler::throwException("Gradeable", $ex);
        }
    }

    /**
     * Get the student details from the database using given student_id
     *
     * @throws \InvalidArgumentException
     */
     
     // TODO USE NEW USER TABLE 
     
    private function setStudentDetails() {
        if (!isset($this->student)) {
            
//GETS THE ALLOWED LATE DAYS FOR A STUDENT AS OF THE SUBMISSION DATE
           /* Database::query("
SELECT s.*, COALESCE(ld.allowed_lates,0) as student_allowed_lates
FROM students as s
LEFT JOIN (
    SELECT allowed_lates
    FROM late_days
    WHERE since_timestamp <= ? and student_id=?
    ORDER BY since_timestamp DESC
) as ld on 1=1
WHERE s.student_id=? LIMIT 1", array($this->eg_details['eg_submission_due_date'], $this->student_id, $this->student_id));
            $this->student = Database::row();
            if ($this->student == array()) {
                throw new \InvalidArgumentException("Could not find student '{$this->student_id}'");
            }

            $params = array($this->student_id, $this->eg_details['g_id']);
            Database::query("SELECT * FROM late_day_exceptions WHERE ex_student_id=? AND ex_rubric_id=?", $params);
            $row = Database::row();
            $this->late_days_exception = (isset($row['ex_late_days'])) ? $row['ex_late_days'] : 0;

            $params = array($this->student_id, $this->eg_details['eg_submission_due_date']);
//TODO factor in the status
//DETERMINE THE NUMBER OF LATE DAYS USED FOR A GRADEABLE 
            Database::query("
SELECT GREATEST(SUM(g.grade_days_late) - COALESCE(SUM(s.ex_late_days),0),0) as used_late_days
FROM grades as g
    LEFT JOIN
    (
        SELECT * FROM late_day_exceptions
    ) as s on s.ex_rubric_id = g.rubric_id and s.ex_student_id=g.student_id
    LEFT JOIN
    (
        SELECT rubric_id, rubric_due_date FROM rubrics
    ) as r on r.rubric_id = g.rubric_id
WHERE g.student_id=? AND r.rubric_due_date<?", $params); 
            $row = Database::row();
            $this->student['used_late_days'] = isset($row['used_late_days']) ? $row['used_late_days'] : 0;
        }*/
            Database::query("SELECT * FROM users WHERE user_id=?", array($this->student_id));
            $this->student = Database::row();
            $this->late_days_exception = 0;
            $this->student['used_late_days'] = 0;
            $this->student['student_allowed_lates'] = 2;
        }
    }

    /**
     * Get the electronic gradeable and previous grade (if available) for the given g_id
     *
     * @throws \InvalidArgumentException
     */
    private function setEGDetails() {
        //CHECK IF THERE IS A GRADEABLE FOR THIS STUDENT 
        
        $eg_details_query = "
SELECT g_title, gd_overall_comment, g_grade_start_date, eg.* FROM electronic_gradeable AS eg 
    INNER JOIN gradeable AS g ON eg.g_id = g.g_id
    INNER JOIN gradeable_data AS gd ON gd.g_id=g.g_id 
        WHERE gd_user_id=? AND g.g_id=?";
        
        Database::query($eg_details_query, array($this->student_id, $this->g_id));
        
        $this->eg_details = Database::row();
        
        // CREATE THE GRADEABLE DATA
        if (empty($this->eg_details)) {
            // TODO FACTOR IN LATE DAYS                     //TODO replace with grader id
            $params = array($this->g_id, $this->student_id, $this->student_id, '', 0,0,1); 
            Database::query("INSERT INTO gradeable_data(g_id,gd_user_id,gd_grader_id,gd_overall_comment, gd_status,gd_late_days_used,gd_active_version) VALUES(?,?,?,?,?,?,?)", $params); 
            $this->gd_id = \lib\Database::getLastInsertId('gradeable_data_gd_id_seq');
            
            Database::query( $eg_details_query, array($this->student_id, $this->g_id));
        
            $this->eg_details = Database::row();
            
        }
        else{
            //get the gd_id 
            //TODO change this to accounts
            $params=array($this->student_id, $this->g_id);
            Database::query("SELECT gd_id FROM gradeable as g INNER JOIN gradeable_data AS gd ON g.g_id=gd.g_id WHERE gd_user_id=? AND g.g_id =?", $params);
            $this->gd_id = Database::row()['gd_id'];
        }

        $this->submission_ids[1] = $this->eg_details['g_id'];
                
        $params = array($this->eg_details['g_id'], $this->gd_id);
        
        //GET ALL questions and scores ASSOCIATED WITH A GRADEABLE
        Database::query("
SELECT gc.*, gcd.*, 
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
    }

    /**
     * Get the files associated with the assignment from the submission directory
     */
    private function setEGSubmissionDetails() {
        
        foreach($this->submission_ids as $submission_id) {
            $submission_directory = implode("/", array(__SUBMISSION_SERVER__, "submissions", $submission_id, $this->student_id));
            if (!file_exists($submission_directory)) {
                continue;
            }

            $objects = scandir($submission_directory);
            $objects = array_filter($objects, function($element) use ($submission_directory) {
                return is_dir($submission_directory."/".$element) && !in_array($element, array('.', '..'));
            });
            sort($objects);
            if (count($objects) > 0) {
                $this->max_assignment = end($objects);
            }
            else {
                continue;
            }

            $details = array();
            $this->submitted = 1;

            if (!$this->has_grade || !isset($this->active_assignment) || $this->active_assignment <= 0) {
                if (file_exists(implode("/", array($submission_directory, "user_assignment_settings.json")))) {
                    $settings = json_decode(file_get_contents(implode("/", array($submission_directory, "user_assignment_settings.json"))), true);
                    $this->active_assignment = $settings['active_assignment'];
                    // If the active_assignment is -1 in the file, then the submission was "cancelled"
                    if ($settings['active_assignment'] == 0) {
                        continue;
                    }
                }
                else {
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
                removeTrailingCommas(file_get_contents(implode("/",array(__SUBMISSION_SERVER__,"config",
                                                                         "build","build_".$submission_id.".json")))), true);
        }
    }

    /**
     * Get result files associated with the assignment
     */
    private function setEGResults() {
        foreach($this->submission_ids as $submission_id) {
            $result_directory = implode("/", array(__SUBMISSION_SERVER__, "results",
                                $submission_id, $this->student_id, $this->active_assignment));

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
                $date_due = strtotime($this->eg_details["eg_submission_due_date"]) + 1 + __SUBMISSION_GRACE_PERIOD_SECONDS__;
                $late_days = round((($date_submission - $date_due) / (60 * 60 * 24)) + .5, 0);
                $late_days = ($late_days < 0) ? 0 : $late_days;
            }
            
            //print "Submission details file path: ". $submission_details; // checked 

            $details['directory'] = $result_directory;

            // We can lazy load the actual results till we need them (such as the diffs, etc.)
            $this->results_details = $details;
            
            //var_dump($this->results_details);
            
            $skip_files = array();
            //$i = 0;
            foreach ($this->results_details['testcases'] as $testcase) {
                if (isset($testcase['execute_logfile'])) {
                    $skip_files[] = $testcase['execute_logfile'];
                }
                if (isset($testcase['compilation_output'])) {
                    $skip_files[] = $testcase['compilation_output'];
                }
                if (isset($testcase['diffs'])){
                    foreach($testcase['diffs'] as $diff) {
                        foreach(array('instructor_file', 'student_file', 'diff_id') as $file) {
                            if(isset($diff[$file])) {
                                $skip_files[] = $diff[$file] . ($file == 'diff_id' ? '.json' : '');
                            }
                        }
                    }
                }
            }

            $this->eg_files = array_merge($this->eg_files, FileUtils::getAllFiles($result_directory, array(), $skip_files));
        }
    }

    /**
     * Calculate the overall status of the electronic gradeable. There is an entire electronic gradeable status which can take
     * on the values of 0 (not accepted) or 1 (accepted) where not accepted just means electronic gradeable
     * should get a 0 automatically and 1 means electronic gradeable should be graded. 
     */
    private function calculateStatus() {
        if (!$this->submitted) {
            return;
        }
        
        //TODO update with late days IMPLEMENT THIS 
        /*
        // IF MORE LATEDAYS WERE USED ON THIS ASSIGNMENT THAN ALLOWED => FAIL
        if ($this->eg_details['rubric_late_days'] >= 0 &&
            $this->parts_days_late_used[$i] > $this->eg_details['rubric_late_days']) {
            //$this->parts_status[$i] = 0;
            $this->status =0;
        }
        // IF MORE LATEDAYS WERE USED THAN THE STUDENT IS ALLOWED => FAIL
        else if ($this->student['student_allowed_lates'] >= 0 &&
            $this->student['used_late_days'] + $this->parts_days_late_used[$i] > $this->student['student_allowed_lates']) {
            //$this->parts_status[$i] = 0;
            $this->status = 0;
        }

        else{
            $this->status = 1;
        }*/
        $this->status = 1;
    }

    /**
     * Calculate the current grade for each question. This is either the saved value in the DB (for a regrade),
     * a 0 if the electronic gradeable wasn't submitted or the ZERO_RUBRIC_GRADES flag is set,
     * otherwise set the question to its potential full value.
     */
    private function setQuestionTotals() {
        $total = 0;
        for ($i = 0; $i < count($this->questions); ++$i) {
            $question = &$this->questions[$i];
            if (!isset($question['gcd_score'])) {
                if ($this->status == 0) {
                    $question['gcd_score'] = 0;
                }
                else if (__USE_AUTOGRADER__ && $question['gc_order']< 2) {
                    $question['gcd_score'] = 0;
                        
                    if ($question['gc_order'] == 1) {
                        $question['gcd_score'] += $this->results_details['non_extra_credit_points_awarded'];
                    }
                    else {
                        $question['gcd_score'] += $this->results_details['extra_credit_points_awarded'];
                    }
                }
                else if (__ZERO_RUBRIC_GRADES__) {
                    $question['gcd_score'] = 0;
                }
                else {
                    $question['gcd_score'] = $question['gc_max_value'];
                }
            }

            if (!$question['gc_is_extra_credit']) {
                $total += $question['gc_max_value'];
            }
        }
        $this->eg_details['eg_total'] = $total; 
    }
}
