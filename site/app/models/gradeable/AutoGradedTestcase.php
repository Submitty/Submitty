<?php

namespace app\models\gradeable;


use app\libraries\Core;
use app\libraries\Utils;
use app\models\AbstractModel;
use app\models\GradeableAutocheck;

/**
 * Class AutoGradedTestcase
 * @package app\models\gradeable
 *
 * @method float getPoints()
 * @method string getMessage()
 * @method GradeableAutocheck[] getAutochecks()
 */
class AutoGradedTestcase extends AbstractModel {

    /** @property @var AutogradingTestcase The reference to the testcase this grade is for */
    private $testcase = null;
    /** @property @var float The number points the submitter earned for this testcase */
    protected $points = 0.0;
    /** @property @var bool If the user can view these results */
    protected $view = true;
    /** @property @var string The message to show the user for this testcase */
    protected $message = '';

    /** @property @var GradeableAutocheck[] */
    protected $autochecks = [];

    public function __construct(Core $core, AutogradingTestcase $testcase, $result_path, array $details) {
        parent::__construct($core);

        if ($testcase === null) {
            throw new \InvalidArgumentException('Testcase instance cannot be null');
        }
        $this->testcase = $testcase;

        // Load simple fields
        $this->view = boolval($details['view_testcase'] ?? true);
        $this->message = Utils::prepareHtmlString($details['testcase_message'] ?? '');

        // Load the autochecks
        if (isset($details['autochecks'])) {
            foreach ($details['autochecks'] as $idx => $autocheck) {
                $index = "id_{$testcase->getIndex()}_{$idx}";
                $this->autochecks[] = new GradeableAutocheck(
                    $this->core, $autocheck,
                    $this->core->getConfig()->getCoursePath(),
                    $result_path, $index
                );
            }
        }

        // Load points earned
        $this->points = floatval($details['points_awarded'] ?? 0);
        if ($testcase->getPoints() > 0) {
            // POSITIVE POINTS TESTCASE
            // TODO: ADD ERROR <--(what does this mean)?
            /*
            $this->points = min(max(0, $this->points), $testcase->getPoints());
            */
        } else if ($testcase->getPoints() < 0) {
            // PENALTY TESTCASE
            // TODO: ADD ERROR <--(what does this mean)?
            $this->points = min(max($testcase->getPoints(), $this->points), 0);
        }
    }

    /**
     * Gets the testcase config associated with this grade
     * @return AutogradingTestcase
     */
    public function getTestcase() {
        return $this->testcase;
    }

    /**
     * Gets if this grade has any additional data to show the user other than
     *  the numeric grade (i.e DiffViewer)
     * @return bool
     */
    public function hasAutochecks() {
        return count($this->autochecks) > 0;
    }

    /**
     * Gets if the user can view this graded testcase
     * @return bool
     */
    public function canView() {
        return $this->view;
    }

    /** @internal */
    public function setPoints() {
        throw new \BadFunctionCallException('Setters disabled for AutoGradedTestcase');
    }

    /** @internal */
    public function setAutochecks() {
        throw new \BadFunctionCallException('Setters disabled for AutoGradedTestcase');
    }

    /** @internal */
    public function setView(){
        throw new \BadFunctionCallException('Setters disabled for AutoGradedTestcase');
    }

    /** @internal */
    public function setMessage() {
        throw new \BadFunctionCallException('Setters disabled for AutoGradedTestcase');
    }
}
