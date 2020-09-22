<?php

namespace app\models\gradeable;

use app\exceptions\NotImplementedException;
use app\libraries\Core;
use app\libraries\Utils;
use app\models\AbstractModel;
use app\libraries\FileUtils;
use app\models\notebook\UserSpecificNotebook;

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
 * @method int getEarlySubmissionMinimumPoints()
 * @method AutogradingTestcase[] getEarlySubmissionTestCases()
 * @method int getTotalNonHiddenNonExtraCredit()
 * @method int getTotalNonHiddenExtraCredit()
 * @method int getTotalHiddenNonExtraCredit()
 * @method int getTotalHiddenExtraCredit()
 * @method bool getOnePartyOnly()
 * @method bool isNotebookGradeable()
 */
class AutogradingConfig extends AbstractModel {

    /** @prop @var int The maximum allowed size (in bytes) of a submission */
    protected $max_submission_size;
    /** @prop @var int The maximum number of submissions allowed */
    protected $max_submissions;
    /** @prop @var string A message to show the user above the file upload box */
    protected $gradeable_message;
    /** @prop @var bool Indicates if list of test should be shown at the bottom of the page */
    protected $hide_version_and_test_details;
    /** @prop @var bool Indicates if list os submitted files should be shown on page */
    protected $hide_submitted_files;
    /** @prop @var string Any additional requirements for worker machine (i.e. "extra_ram")  */
    protected $required_capabilities;
    /** @prop @var int The number of seconds allowed for autograding */
    protected $max_possible_grading_time = -1;

    /** @prop @var string[] The names of different upload bins on the submission page (1-indexed) */
    protected $part_names = [];
    /** @prop @var bool Variable representing if only one of the available parts can be used for submission */
    protected $one_part_only;

    /** @prop @var array Array of notebook objects */
    protected $notebook_config = [];
    /** @prop @var AutogradingTestcase[] Cut-down information about autograding test cases*/
    private $testcases = [];

    /* Properties if early submission incentive enabled */
    /** @prop @var bool If there is an early submission incentive */
    private $early_submission_incentive = false;
    /** @prop @var string The message given to describe the early submission */
    protected $early_submission_message = '';
    /** @prop @var int The minimum number days early to receive the early submission incentive */
    protected $early_submission_minimum_days_early = 0;
    /** @prop @var int The minimum number of points required to receive the early submission incentive */
    protected $early_submission_minimum_points = 0;
    /** @prop @var AutogradingTestcase[] The test cases for which the points must be earned to satisfy the incentive */
    protected $early_submission_test_cases = [];
    /** @prop @var bool */
    protected $notebook_gradeable = false;

    /* Property if load message alert is enabled */
    /** @prop @var bool If there is a message to show on Gradeable load */
    private $load_gradeable_message_enabled = false;
    /** @prop @var string The message to show to the user before letting them go to the gradeable */
    protected $load_gradeable_message = '';
    /** @prop @var bool If the message should only be shown to the user if they haven't opened the gradeable yet */
    protected $load_gradeable_message_first_time_only = false;

    /* Properties accumulated from the AutogradingTestcases */

    /** @prop @var int Total number of non-hidden non-extra-credit ('normal') points for all test cases */
    protected $total_non_hidden_non_extra_credit = 0;
    /** @prop @var int Total number of non-hidden extra-credit points for all test cases */
    protected $total_non_hidden_extra_credit = 0;
    /** @prop @var int Total number of hidden non-extra-credit points for all test cases */
    protected $total_hidden_non_extra_credit = 0;
    /** @prop @var int Total number of hidden extra-credit points for all test cases */
    protected $total_hidden_extra_credit = 0;


    public function __construct(Core $core, array $details) {
        parent::__construct($core);

        // Was there actually a config file to read from
        if ($details === null || $details === []) {
            throw new \InvalidArgumentException('Provided details were blank or null');
        }

        $this->max_submission_size = floatval($details['max_submission_size'] ?? 0);
        $this->max_submissions = intval($details['max_submissions'] ?? 0);
        if (isset($details['assignment_message'])) {
            $this->gradeable_message = $details['assignment_message'] ?? '';
        }
        elseif (isset($details['gradeable_message'])) {
            $this->gradeable_message = $details['gradeable_message'] ?? '';
        }

        if (isset($details['load_gradeable_message'])) {
            $this->load_gradeable_message_enabled = true;
            $this->load_gradeable_message = $details['load_gradeable_message']['message'] ?? '';
            $this->load_gradeable_message_first_time_only = $details['load_gradeable_message']['first_time_only'] ?? false;
        }

        // These two items default to false if they don't exist in the gradeable config.json
        $this->hide_version_and_test_details = $details['hide_version_and_test_details'] ?? false;
        $this->hide_submitted_files = $details['hide_submitted_files'] ?? false;

        $this->required_capabilities = $details['required_capabilities'] ?? 'default';
        $this->max_possible_grading_time = $details['max_possible_grading_time'] ?? -1;

        $this->parseTestCases($details);

        if (isset($details['early_submission_incentive'])) {
            $this->early_submission_incentive = true;
            $this->early_submission_message = Utils::prepareHtmlString($details['early_submission_incentive']['message'] ?? '');
            $this->early_submission_minimum_days_early = intval($details['early_submission_incentive']['minimum_days_early'] ?? 0);
            $this->early_submission_minimum_points = intval($details['early_submission_incentive']['minimum_points'] ?? 0);
            foreach ($details['early_submission_incentive']['test_cases'] ?? [] as $testcase) {
                $this->early_submission_test_cases[] = $this->testcases[$testcase];
            }
        }

        if (isset($details['notebook'])) {
            $this->notebook_config = $details['notebook'];
            $this->notebook_gradeable = true;
        }

        // defaults num of parts to 1 if value is not set
        $num_parts = count($details['part_names'] ?? [1]);

        // Get all of the part names
        for ($i = 1; $i <= $num_parts; $i++) {
            $j = $i - 1;
            if (
                isset($details['part_names'])
                && isset($details['part_names'][$j])
                && trim($details['part_names'][$j]) !== ""
            ) {
                $this->part_names[$i] = $details['part_names'][$j];
            }
            else {
                $this->part_names[$i] = "Part " . $i;
            }
        }
    }


    public function toArray() {
        $details = parent::toArray();

        $details['testcases'] = parent::parseObject($this->testcases);
        return $details;
    }

    /**
     * Gets the test cases for this configuration
     * @return AutogradingTestcase[]
     */
    public function getTestcases() {
        return $this->testcases;
    }


    public function getUserSpecificNotebook(string $user_id, string $gradeable_id): UserSpecificNotebook {
        $notebook = new UserSpecificNotebook(
            $this->core,
            $this->notebook_config,
            $gradeable_id,
            $user_id
        );

        $this->parseTestCases($notebook->getTestCases());

        return $notebook;
    }


    private function parseTestCases(array $details): void {
        if (!isset($details['testcases'])) {
            return;
        }

        $previous_testcase_count = count($this->testcases);
        foreach ($details['testcases'] as $idx => $testcase_details) {
            //if there are already existing testcases, add these to the end
            $index = $idx + $previous_testcase_count;
            $testcase = new AutogradingTestcase($this->core, $testcase_details, $index);

            $this->updateTestCasePoints($testcase);
            $this->testcases[$index] = $testcase;
        }
    }

    private function updateTestCasePoints($testcase) {
        // Accumulate only the positive points
        $points = $testcase->getPoints();
        if ($points >= 0.0) {
            if ($testcase->isHidden()) {
                if ($testcase->isExtraCredit()) {
                    $this->total_hidden_extra_credit += $points;
                }
                else {
                    $this->total_hidden_non_extra_credit += $points;
                }
            }
            else {
                if ($testcase->isExtraCredit()) {
                    $this->total_non_hidden_extra_credit += $points;
                }
                else {
                    $this->total_non_hidden_non_extra_credit += $points;
                }
            }
        }
    }


    public function updateTestCases($test_cases) {
        $this->parseTestCases($test_cases);
    }

    /**
     * Gets whether a load message should be loaded
     * @return bool
     */
    public function hasLoadGradeableMessageEnabled($gradeable_id, $user_id) : bool {
        return $this->load_gradeable_message_enabled && (!$this->load_gradeable_message_first_time_only || count($this->core->getQueries()->getGradeableAccessUser($gradeable_id, $user_id)) === 0);
    }

    /**
     * Returns the load message
     * @return string
     */
    public function getLoadGradeableMessage() : string {
        return $this->load_gradeable_message;
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
        return max(
            $this->total_hidden_non_extra_credit,
            $this->total_non_hidden_non_extra_credit,
            $this->total_hidden_extra_credit,
            $this->total_non_hidden_extra_credit
        ) > 0;
    }

    /**
     * Gets if there are any user-viewable testcases
     * @return bool
     */
    public function anyVisibleTestcases() {
        /** @var AutogradingTestcase $testcase */
        foreach ($this->testcases as $testcase) {
            if (!$testcase->isHidden()) {
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
