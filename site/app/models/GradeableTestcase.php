<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\DiffViewer;
use app\libraries\Utils;

/**
 * Class GradeableTestcase
 *
 * Contains information pertaining to individual auto-grader test cases that are
 * detailed in the config/build/build_*.json files. Additionally, can load in information
 * from a results.json file to set additional information pertaining to each testcase.
 * We only really need to do this for the version we're actually looking at and no others
 * as we don't need that high level of information (as we really only ever need late days,
 * points awarded, and if it's the active version).
 */
class GradeableTestcase {
    private $core;
    
    private $index;
    
    /** @var string */
    private $name = "";
    /** @var string */
    private $details = "";
    private $view_testcase = true;
    /** @var float */
    private $points = 0;
    private $extra_credit = false;
    private $hidden = false;
    /** @var float */
    private $points_awarded = 0;
    private $log_file = "";
    private $autochecks = array();
    
    public function __construct(Core $core, $testcase, $idx) {
        $this->core = $core;
        $this->index = $idx;
        
        if (isset($testcase['title'])) {
            $this->name = Utils::prepareHtmlString($testcase['title']);
        }
        if (isset($testcase['details'])) {
            $this->details = $testcase['details'];
        }
        if (isset($testcase['points'])) {
            $this->points = floatval($testcase['points']);
        }
        if (isset($testcase['extra_credit'])) {
            $this->extra_credit = $testcase['extra_credit'] === true;
        }
        if (isset($testcase['hidden'])) {
            $this->hidden = $testcase['hidden'] === true;
        }
    }
    
    public function addResultTestcase($testcase, $result_path) {
        if (isset($testcase['autochecks'])) {
            foreach ($testcase['autochecks'] as $idx => $autocheck) {
                $index = "id_{$this->index}_{$idx}";
                $this->autochecks[] = new GradeableAutocheck($autocheck,
                                                             $this->core->getConfig()->getCoursePath(),
                                                             $result_path, $index);
            }
        }
        
        if (isset($testcase['points_awarded'])) {
            $this->points_awarded = floatval($testcase['points_awarded']);
            if ($this->points > 0) {
              // POSITIVE POINTS TESTCASE
              if ($this->points_awarded < 0) {
                // TODO: ADD ERROR
                $this->points_awarded = 0;
              }
              if ($this->points_awarded > $this->points) {
                // TODO: ADD ERROR
                $this->points_awarded = $this->points;
              }
            } else if ($this->points < 0) {
              // PENALTY TESTCASE
              if ($this->points_awarded > 0) {
                // TODO: ADD ERROR
                $this->points_awarded = 0;
              }
              if ($this->points_awarded < $this->points) {
                // TODO: ADD ERROR
                $this->points_awarded = $this->points;
              }
            } else {
              $this->points_awarded = 0;
            }
        }
        if (isset($testcase['view_testcase'])) {
            $this->view_testcase = $testcase['view_testcase'];
        }
    }
    
    public function getName() {
        return $this->name;
    }
    
    public function getDetails() {
        return $this->details;
    }

    public function viewTestcase() {
      return $this->view_testcase;
    }
    
    public function getPoints() {
        return $this->points;
    }
    
    public function getNonHiddenPoints() {
        return (!$this->isHidden()) ? $this->points : 0;
    }
    
    public function getNonHiddenNonExtraCreditPoints() {
        return (!$this->isHidden() && !$this->isExtraCredit()) ? $this->points : 0;
    }
    
    public function getPointsAwarded() {
        return $this->points_awarded;
    }
    
    public function hasPoints() {
        return $this->points != 0;
    }
    
    public function isHidden() {
        return $this->hidden;
    }
    
    public function isExtraCredit() {
        return $this->extra_credit;
    }
    
    public function getLogfile() {
        return $this->log_file;
    }
    
    /**
     *
     * @return GradeableAutocheck[]
     */
    public function getAutochecks() {
        return $this->autochecks;
    }
    
    public function hasDetails() {
      return (!$this->isHidden() && count($this->autochecks) > 0);
    }
}