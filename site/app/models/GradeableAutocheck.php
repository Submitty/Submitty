<?php

namespace app\models;

use app\libraries\DiffViewer;
use app\libraries\Utils;

/**
 * Class GradeableAutocheck
 *
 * Contains information pertaining to the autocheck element that's contained within a
 * GradeableTestcase. There is 0+ autochecks per GradeableTestcase.
 */
class GradeableAutocheck {
    
    private $id;
    
    /** @var DiffViewer DiffViewer instance to hold the student, instructor, and differences */
    private $diff_viewer;
    
    /** @var string Description to show for displaying the diff */
    private $description = "";
    
    /** @var string Message to show underneath the description for a diff */
    private $message = "";
    
    public function __construct($details, $course_path, $result_path) {
        $this->id = $details['autocheck_id'];
        
        if (isset($details['description'])) {
            $this->description = Utils::prepareHtmlString($details['description']);
        }
        
        if (isset($details['message'])) {
            $this->message = Utils::prepareHtmlString($details['message']);
        }
        
        $student_file = $instructor_file = $difference_file = "";
        if(isset($details['student_file']) && file_exists($result_path . "/" . $details['student_file'])) {
            $student_file = $result_path . "/" . $details['student_file'];
        }
    
        if(isset($details['instructor_file']) &&
            file_exists($course_path . "/" . $details['instructor_file'])) {
            $instructor_file = $course_path . "/" . $details['instructor_file'];
        }
    
        if(isset($details['difference']) && file_exists($result_path . "/" . $details['difference'])) {
            $difference_file = $result_path . "/" . $details['difference'];
        }
        
        $this->diff_viewer = new DiffViewer($student_file, $instructor_file, $difference_file);
    }
    
    public function getId() {
        return $this->id;
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