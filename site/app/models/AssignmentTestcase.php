<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\DiffViewer;

class AssignmentTestcase {
    private $core;
    private $testcase;
    private $log_file = "";
    private $compilation_output = "";
    private $student_file = "";
    private $instructor_file = "";
    private $difference_file = "";
    private $diffs = array();
    
    public function __construct(Core $core, $testcase) {
        $this->core = $core;
        $this->testcase = $testcase;
    }
    
    public function addResultTestcase($testcase, $result_path) {
        $this->testcase = array_merge($this->testcase, $testcase);
        if (file_exists($result_path."/".$this->testcase['execute_logfile'])) {
            $this->log_file = file_get_contents($result_path . "/" . $this->testcase['execute_logfile']);
        }
        
        if (isset($this->testcase['compilation_output']) &&
            file_exists($result_path . "/" . $this->testcase['compilation_output'])) {
                $this->compilation_output = file_get_contents($result_path . "/" . $this->testcase['compilation_output']);
        }
        
        foreach ($this->testcase['diffs'] as $diff) {
            $student_file = $instructor_file = $difference_file = "";
            if(isset($diff['student_file']) && file_exists($result_path . "/" . $diff['student_file'])) {
                $student_file = $result_path . "/" . $diff['student_file'];
            }
            
            if(isset($diff['instructor_file']) && file_exists($this->core->getConfig()->getCoursePath() . "/" . $diff['instructor_file'])) {
                $instructor_file = $this->core->getConfig()->getCoursePath() . "/" . $diff['instructor_file'];
            }
    
            if(isset($diff['difference']) && file_exists($result_path . "/" . $diff['difference'])) {
                $difference_file = $result_path . "/" . $diff['difference'];
            }
            $this->diffs[] = array_merge($diff, array('diff_viewer' => new DiffViewer($student_file,
                                                                                      $instructor_file,
                                                                                      $difference_file)));
        }
    }
    
    public function getName() {
        return $this->testcase['title'];
    }
    
    public function getCommand() {
        return $this->testcase['details'];
    }
    
    public function getPoints() {
        return $this->testcase['points'];
    }
    
    public function getNonHiddenPoints() {
        if (!$this->isHidden()) {
            return $this->testcase['points'];
        }
        else {
            return 0;
        }
    }
    
    public function getNormalPoints() {
        if (!$this->isHidden() && !$this->isExtraCredit()) {
            return $this->testcase['points'];
        }
        else {
            return 0;
        }
    }
    
    public function getPointsAwarded() {
        return $this->testcase['points_awarded'];
    }
    
    public function hasPoints() {
        return $this->testcase['points'] > 0;
    }
    
    public function isHidden() {
        return isset($this->testcase['hidden']) && $this->testcase['hidden'] === true;
    }
    
    public function isExtraCredit() {
        return isset($this->testcase['extracredit']) && $this->testcase['extracredit'] === true;
    }
    
    public function hasExecuteLog() {
        return trim($this->log_file) !== "";
    }
    
    public function hasCompilationOutput() {
        return trim($this->compilation_output) !== "";
    }
    
    public function hasStudentFile() {
        return $this->student_file !== "";
    }
    
    public function hasInstructorFile() {
        return $this->instructor_file !== "";
    }
    
    public function hasDifferenceFile() {
        return $this->difference_file !== "";
    }
    
    public function getLogfile() {
        return $this->log_file;
    }
    
    public function getCompilationOutput() {
        return $this->compilation_output;
    }
    
    /**
     *
     * @return array
     */
    public function getDiffs() {
        return $this->diffs;
    }
}