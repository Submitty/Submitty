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
 *
 * @method string getName()
 * @method string getDetails()
 * @method float getPoints()
 * @method float getPointsAwarded()
 * @method string getLogFile()
 * @method GradeableAutocheck[] getAutochecks()
 * @method string getTestcaseMessage()
 */
class GradeableTestcase extends AbstractModel {
    protected $core;

    /** @property @var string */
    protected $index;
    
    /** @property @var string */
    protected $name = "";
    /** @property @var string */
    protected $details = "";
    protected $view_testcase = true;
    /** @property @var float */
    protected $points = 0;
    protected $extra_credit = false;
    protected $hidden = false;
    /** @property @var float */
    protected $points_awarded = 0;
    /** @proprety @var string */
    protected $log_file = "";
    /** @property @var GradeableAutocheck[] */
    protected $autochecks = array();
    /** @property @var string */
    protected $testcase_message = "";
    /** @property @var bool */
    protected $view_testcase_message = true;

    /**
     * GradeableTestcase constructor.
     *
     * @param Core  $core
     * @param array $testcase
     * @param int   $idx
     */
    public function __construct(Core $core, $testcase, $idx) {
        parent::__construct($core);
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
         if (isset($testcase['view_testcase_message'])) {
            $this->view_testcase_message = $testcase['view_testcase_message'] === true;
        }
    }
    
    public function addResultTestcase($testcase, $result_path) {
        if (isset($testcase['autochecks'])) {
            foreach ($testcase['autochecks'] as $idx => $autocheck) {
                $index = "id_{$this->index}_{$idx}";
                $this->autochecks[] = new GradeableAutocheck($this->core, $autocheck,
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
                  //$this->points_awarded = 0;
                }
                if ($this->points_awarded > $this->points) {
                  // TODO: ADD ERROR
                  //$this->points_awarded = $this->points;
                }
            }
            else if ($this->points < 0) {
                // PENALTY TESTCASE
                if ($this->points_awarded > 0) {
                    // TODO: ADD ERROR
                    $this->points_awarded = 0;
                }
                if ($this->points_awarded < $this->points) {
                    // TODO: ADD ERROR
                    $this->points_awarded = $this->points;
                }
            }
            else {
                $this->points_awarded = 0;
            }
        }
        if (isset($testcase['view_testcase'])) {
            $this->view_testcase = $testcase['view_testcase'];
        }
        if (isset($testcase['testcase_message'])) {
            $this->testcase_message = Utils::prepareHtmlString($testcase['testcase_message']);
        }
    }

    public function viewTestcase() {
      return $this->view_testcase;
    }

    public function getNonHiddenPoints() {
        return (!$this->isHidden()) ? $this->points : 0;
    }
    
    public function getNonHiddenNonExtraCreditPoints() {
        return (!$this->isHidden() && !$this->isExtraCredit()) ? $this->points : 0;
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
    
    public function hasDetails() {
      return (count($this->autochecks) > 0);
    }

    public function viewTestcaseMessage() {
        return $this->view_testcase_message;
    }
}
