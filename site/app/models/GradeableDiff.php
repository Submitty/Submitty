<?php

namespace app\models;

use app\libraries\DiffViewer;
use app\libraries\Utils;

/**
 * Class GradeableDiff
 *
 * Contains information pertaining to a Diff that's contained within a GradeableTestcase
 * (though there can be more than one).
 */
class GradeableDiff {
    
    /** @var DiffViewer DiffViewer instance to hold the student, instructor, and differences */
    private $diff_viewer;
    /** @var string Description to show for displaying the diff */
    private $description = "";
    /** @var string Message to show underneath the description for a diff */
    private $message = "";
    
    public function __construct($diff, $course_path, $result_path) {
        if (isset($details['description'])) {
            $this->description = Utils::prepareHtmlString($details['description']);
        }
        
        if (isset($details['message'])) {
            $this->message = Utils::prepareHtmlString($details['message']);
        }
        
        $student_file = $instructor_file = $difference_file = "";
        if(isset($diff['student_file']) && file_exists($result_path . "/" . $diff['student_file'])) {
            $student_file = $result_path . "/" . $diff['student_file'];
        }
    
        if(isset($diff['instructor_file']) &&
            file_exists($course_path . "/" . $diff['instructor_file'])) {
            $instructor_file = $course_path . "/" . $diff['instructor_file'];
        }
    
        if(isset($diff['difference']) && file_exists($result_path . "/" . $diff['difference'])) {
            $difference_file = $result_path . "/" . $diff['difference'];
        }
        $this->diff_viewer = new DiffViewer($student_file, $instructor_file, $difference_file);
    }
    
    /**
     * @return DiffViewer
     */
    public function getDiffViewer() {
        return $this->diff_viewer;
    }
    
    /**
     * @return string
     */
    public function getDescription() {
        return $this->description;
    }
    
    /**
     * @return string
     */
    public function getMessage() {
        return $this->message;
    }
}