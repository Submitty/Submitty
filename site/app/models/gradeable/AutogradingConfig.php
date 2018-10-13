<?php

namespace app\models\gradeable;


use app\libraries\Core;
use app\libraries\Utils;
use app\models\AbstractModel;
use app\models\GradeableTestcase;

/**
 * Class AutogradingConfig
 * @package app\models\gradeable
 *
 * TODO: evaluate which fields need to be loaded from the config file
 *
 * @method int getMaxSubmissionSize()
 * @method int getMaxSubmissions()
 * @method string getAssignmentMessage()
 * @method string getRequiredCapabilities()
 * @method float getMaxPossibleGradingTime()
 * @method string[] getPartNames()
 * @method string getEarlySubmissionMessage()
 * @method int getEarlySubmissionMinimumDaysEarly()
 * @method float getEarlySubmissionMinimumPoints()
 * @method GradeableTestcase[] getEarlySubmissionTestCases()
 * @method float getTotalNonHiddenNonExtraCredit()
 * @method float getTotalNonHiddenExtraCredit()
 * @method float getTotalHiddenNonExtraCredit()
 * @method float getTotalHiddenExtraCredit()
 */
class AutogradingConfig extends AbstractModel {

    /** @property @var int The maximum allowed size (in bytes) of a submission */
    protected $max_submission_size;
    /** @property @var int The maximum number of submissions allowed */
    protected $max_submissions;
    /** @property @var string A message to show the user above the file upload box */
    protected $assignment_message;

    /** @property @var string Any additional requirements for worker machine (i.e. "extra_ram")  */
    protected $required_capabilities;
    /** @property @var float The number of seconds allowed for autograding */
    protected $max_possible_grading_time = -1;

    /** @property @var string[] The names of different upload bins on the submission page (1-indexed) */
    protected $part_names = [];

    /** @property @var SubmissionTextBox[] Text box configs for text box submissions*/
    private $textboxes = [];
    /** @property @var AutogradingTestcase[] Cut-down information about autograding test cases*/
    private $testcases = [];

    /* Properties if early submission incentive enabled */
    /** @property @var bool If there is an early submission incentive */
    private $early_submission_incentive = false;
    /** @property @var string The message given to describe the early submission */
    protected $early_submission_message = '';
    /** @property @var int The minimum number days early to receive the early submission incentive */
    protected $early_submission_minimum_days_early = 0;
    /** @property @var int The minimum number of points required to receive the early submission incentive */
    protected $early_submission_minimum_points = 0;
    /** @property @var GradeableTestcase[] The test cases for which the points must be earned to satisfy the incentive */
    protected $early_submission_test_cases = [];


    /* Properties accumulated from GradeableTestcase's */

    /** @property @var float Total number of non-hidden non-extra-credit ('normal') points for all test cases */
    protected $total_non_hidden_non_extra_credit = 0;
    /** @property @var float Total number of non-hidden extra-credit points for all test cases */
    protected $total_non_hidden_extra_credit = 0;
    /** @property @var float Total number of hidden non-extra-credit points for all test cases */
    protected $total_hidden_non_extra_credit = 0;
    /** @property @var float Total number of hidden extra-credit points for all test cases */
    protected $total_hidden_extra_credit = 0;

    public function __construct(Core $core, array $details) {
        parent::__construct($core);

        // Was there actually a config file to read from
        if ($details === null || $details === []) {
            throw new \InvalidArgumentException('Provided details were blank or null');
        }

        $this->max_submission_size = floatval($details['max_submission_size'] ?? 0);
        $this->max_submissions = intval($details['max_submissions'] ?? 0);
        $this->assignment_message = Utils::prepareHtmlString($details['assignment_message'] ?? '');

        $this->required_capabilities = $details['required_capabilities'] ?? 'default';
        $this->max_possible_grading_time = $details['max_possible_grading_time'] ?? -1;

        if (isset($details['testcases'])) {
            foreach ($details['testcases'] as $idx => $testcase_details) {
                $testcase = new AutogradingTestcase($this->core, $testcase_details, $idx);

                // Accumulate only the positive points
                $points = $testcase->getPoints();
                if($points >= 0.0) {
                    if ($testcase->isHidden()) {
                        if ($testcase->isExtraCredit()) {
                            $this->total_hidden_extra_credit += $points;
                        } else {
                            $this->total_hidden_non_extra_credit += $points;
                        }
                    } else {
                        if ($testcase->isExtraCredit()) {
                            $this->total_non_hidden_extra_credit += $points;
                        } else {
                            $this->total_non_hidden_non_extra_credit += $points;
                        }
                    }
                }

                $this->testcases[$idx] = $testcase;
            }
        }

        if (isset($details['early_submission_incentive'])) {
            $this->early_submission_incentive = true;
            $this->early_submission_message = Utils::prepareHtmlString($details['early_submission_incentive']['message'] ?? '');
            $this->early_submission_minimum_days_early = intval($details['early_submission_incentive']['minimum_days_early'] ?? 0);
            $this->early_submission_minimum_points = intval($details['early_submission_incentive']['minimum_points'] ?? 0);
            foreach ($details['early_submission_incentive']['test_cases'] ?? [] as $testcase) {
                $this->early_submission_test_cases[] = $this->testcases[$testcase];
            }
        }

        // defaults to 1 if no set
        $num_parts = count($details['part_names'] ?? [1]);

        // defaults to 0 if not set
        $num_textboxes = count($details['textboxes'] ?? []);

        // Get all of the part names
        for ($i = 1; $i <= $num_parts; $i++) {
            $j = $i - 1;
            if (isset($details['part_names']) && isset($details['part_names'][$j]) &&
                trim($details['part_names'][$j]) !== "") {
                $this->part_names[$i] = $details['part_names'][$j];
            } else {
                $this->part_names[$i] = "Part " . $i;
            }
        }

        // Get textbox details
        for ($i = 0; $i < $num_textboxes; $i++) {
            $this->textboxes[$i] = new SubmissionTextBox($this->core, $details['textboxes'][$i]);
        }
    }

    public function toArray() {
        $details = parent::toArray();

        $details['testcases'] = parent::parseObject($this->testcases);
        $details['textboxes'] = parent::parseObject($this->textboxes);

        return $details;
    }

    /**
     * Gets the test cases for this configuration
     * @return AutogradingTestcase[]
     */
    public function getTestcases() {
        return $this->testcases;
    }

    /**
     * Gets the text boxes for this configuration
     * @return SubmissionTextBox[]
     */
    public function getTextboxes() {
        return $this->textboxes;
    }

    /**
     * Gets whether this config has an early submission incentive
     * @return bool
     */
    public function hasEarlySubmissionIncentive() {
        return $this->early_submission_incentive;
    }

    /**
     * Gets the number of submission parts
     * @return int
     */
    public function getNumParts() {
        return count($this->getPartNames());
    }

    /**
     * Gets the number of text boxes on the submission page
     * @return int
     */
    public function getNumTextBoxes() {
        return count($this->getTextboxes());
    }

    /**
     * Gets the number of non-hidden points possible for this assignment (including extra credit)
     * @return int
     */
    public function getTotalNonHidden() {
        return $this->total_non_hidden_non_extra_credit + $this->total_non_hidden_extra_credit;
    }

    /**
     * Gets the number of non-extra-credit points possible for this assignment
     * @return int
     */
    public function getTotalNonExtraCredit() {
        return $this->total_non_hidden_non_extra_credit + $this->total_hidden_non_extra_credit;
    }

    /**
     * Gets if this autograding config has any points associated with it
     * @return bool
     */
    public function anyPoints() {
        return max($this->total_hidden_non_extra_credit,
                $this->total_non_hidden_non_extra_credit,
                $this->total_hidden_extra_credit,
                $this->total_non_hidden_extra_credit) > 0;
    }

    /**
     * Gets if there are any user-viewable testcases
     * @return bool
     */
    public function anyVisibleTestcases() {
        /** @var AutogradingTestcase $testcase */
        foreach($this->testcases as $testcase) {
            if(!$testcase->isHidden()) {
                return true;
            }
        }
        return false;
    }

    /* Disabled setters */

    /** @internal */
    public function setMaxSubmissionSize() {
        throw new \BadFunctionCallException('Setters disabled for AutogradingConfig');
    }

    /** @internal */
    public function setMaxSubmissions() {
        throw new \BadFunctionCallException('Setters disabled for AutogradingConfig');
    }

    /** @internal */
    public function setRequiredCapabilities() {
        throw new \BadFunctionCallException('Setters disabled for AutogradingConfig');
    }

    /** @internal */
    public function setMaxPossibleGradingTime() {
        throw new \BadFunctionCallException('Setters disabled for AutogradingConfig');
    }

    /** @internal */
    public function setPartNames() {
        throw new \BadFunctionCallException('Setters disabled for AutogradingConfig');
    }

    /** @internal */
    public function setEarlySubmissionMessage() {
        throw new \BadFunctionCallException('Setters disabled for AutogradingConfig');
    }

    /** @internal */
    public function setEarlySubmissionMinimumDaysEarly() {
        throw new \BadFunctionCallException('Setters disabled for AutogradingConfig');
    }

    /** @internal */
    public function setEarlySubmissionMinimumPoints() {
        throw new \BadFunctionCallException('Setters disabled for AutogradingConfig');
    }

    /** @internal */
    public function setEarlySubmissionTestCases() {
        throw new \BadFunctionCallException('Setters disabled for AutogradingConfig');
    }

    /** @internal */
    public function setTotalNonHiddenNonExtraCredit() {
        throw new \BadFunctionCallException('Setters disabled for AutogradingConfig');
    }

    /** @internal */
    public function setTotalNonHiddenExtraCredit() {
        throw new \BadFunctionCallException('Setters disabled for AutogradingConfig');
    }

    /** @internal */
    public function setTotalHiddenNonExtraCredit() {
        throw new \BadFunctionCallException('Setters disabled for AutogradingConfig');
    }

    /** @internal */
    public function setTotalHiddenExtraCredit() {
        throw new \BadFunctionCallException('Setters disabled for AutogradingConfig');
    }
}
