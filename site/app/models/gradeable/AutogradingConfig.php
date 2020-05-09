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
    private $notebook = [];
    /** @prop @var AbstractGradingInput[] Grading input configs for all new types of gradeable input*/
    private $inputs = [];
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


    /* Properties accumulated from the AutogradingTestcases */

    /** @prop @var int Total number of non-hidden non-extra-credit ('normal') points for all test cases */
    protected $total_non_hidden_non_extra_credit = 0;
    /** @prop @var int Total number of non-hidden extra-credit points for all test cases */
    protected $total_non_hidden_extra_credit = 0;
    /** @prop @var int Total number of hidden non-extra-credit points for all test cases */
    protected $total_hidden_non_extra_credit = 0;
    /** @prop @var int Total number of hidden extra-credit points for all test cases */
    protected $total_hidden_extra_credit = 0;
    /** @prop @var bool have we parsed the notebook config and created a userspecific notebook */
    private $is_notebook_parsed = false;


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

        // These two items default to false if they don't exist in the gradeable config.json
        $this->hide_version_and_test_details = $details['hide_version_and_test_details'] ?? false;
        $this->hide_submitted_files = $details['hide_submitted_files'] ?? false;

        $this->required_capabilities = $details['required_capabilities'] ?? 'default';
        $this->max_possible_grading_time = $details['max_possible_grading_time'] ?? -1;

    
        if (isset($details['testcases'])) {
            foreach ($details['testcases'] as $idx => $testcase_details) {
                $testcase = new AutogradingTestcase($this->core, $testcase_details, $idx);

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


        if (isset($details['notebook'])) {
            $this->notebook = [
                'notebook' => $details['notebook'],
                'item_pool' => []
            ];

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


    //TODO: remove once we can store notebook in its own model
    public function parseNotebook(array $details): void {
        // Setup $this->notebook
        $actual_input = array();
        $this->notebook = [];

        // For each item in the notebook array inside the $details collect data and assign to variables in
        // $this->notebook
        for ($i = 0; $i < count($details['notebook']); $i++) {
            $notebook_cell = $details['notebook'][$i];
            $do_add = true;

            // If cell is of markdown type then figure out if it is markdown_string or markdown_file and pass this
            // markdown forward as 'data' as opposed to 'string' or 'file'
            if (
                isset($notebook_cell['type'])
                && $notebook_cell['type'] === 'markdown'
            ) {
                $markdown = $this->getMarkdownData($notebook_cell);

                // Remove string or file from $notebook_cell
                unset($notebook_cell['markdown_string']);
                unset($notebook_cell['markdown_file']);

                // Read as data
                $notebook_cell['markdown_data'] = $markdown;

                // If next entry is an input type, we assign this as a label - otherwise it is plain markdown
                if ($i < count($details['notebook']) - 1) {
                    $next_cell = &$details['notebook'][$i + 1];
                    if (
                        isset($next_cell['type'])
                        && ($next_cell['type'] == 'short_answer' || $next_cell['type'] == 'multiple_choice')
                    ) {
                        $next_cell['label'] = $markdown;
                        // Do not add current cell to notebook, since it is embedded in the label
                        $do_add = false;
                    }
                }
            }
            elseif (
                $notebook_cell['type'] === 'short_answer'
                && !empty($notebook_cell['programming_language'])
                && empty($notebook_cell['codemirror_mode'])
            ) {
                $notebook_cell['codemirror_mode'] = Utils::getCodeMirrorMode($notebook_cell['programming_language']);
            }

            // Add this cell $this->notebook
            if ($do_add) {
                $this->notebook[] = $notebook_cell;
            }

            // If cell is a type of input add it to the $actual_inputs array
            if (in_array($notebook_cell['type'], ['short_answer', 'multiple_choice'])) {
                $actual_input[] = $notebook_cell;
            }
        }
        

        // Setup $this->inputs
        for ($i = 0; $i < count($actual_input); $i++) {
            if ($actual_input[$i]['type'] == 'short_answer') {
                // If programming language is set then this is a codebox, else regular textbox
                if (isset($actual_input[$i]['programming_language'])) {
                    $this->inputs[$i] = new SubmissionCodeBox($this->core, $actual_input[$i]);
                }
                else {
                    $this->inputs[$i] = new SubmissionTextBox($this->core, $actual_input[$i]);
                }
            }
            elseif ($actual_input[$i]['type'] == 'multiple_choice') {
                $actual_input[$i]['allow_multiple'] = $actual_input[$i]['allow_multiple'] ?? false;

                $this->inputs[$i] = new SubmissionMultipleChoice($this->core, $actual_input[$i]);
            }
        }

        if (isset($details['item_pool'])) {
            $this->notebook['item_pool'] = $details['item_pool'];
        }

        // defaults num of parts to 1 if value is not set
        $num_parts = count($details['part_names'] ?? [1]);
        $this->one_part_only = $details['one_part_only'] ?? false;

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


        if (isset($details['testcases'])) {
            foreach ($details['testcases'] as $idx => $testcase_details) {
                $testcase = new AutogradingTestcase($this->core, $testcase_details, $idx);

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

                $this->testcases[$idx] = $testcase;
            }
        }
    }


    private function getMarkdownData($cell) {
        // If markdown_string is set then just return that
        if (isset($cell['markdown_string'])) {
            return $cell['markdown_string'];
        }
        elseif (isset($cell['markdown_file'])) {
            // Else if markdown_file is set then read the file and return its contents
            // TODO: Implement reading from markdown_file and passing that along
            throw new NotImplementedException("Reading from a markdown_file is not yet implemented.");
        }
        else {
            // Else something unexpected happened
            throw new \InvalidArgumentException("An error occured parsing notebook data.\n" .
                "Markdown configuration may only specify one of 'markdown_string' or 'markdown_file'");
        }
    }

    public function toArray() {
        $details = parent::toArray();

        $details['testcases'] = parent::parseObject($this->testcases);
        $details['inputs'] = parent::parseObject($this->inputs);

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
     * Gets the abstract inputs for this configuration
     * @return AbstractGradeableInput[]
     */
    public function getInputs() {
        return $this->inputs;
    }

    /**
    * creates the notebook by parsing the notebook config and creating a userspecific notebook
    * @param string $gradeable_id
    * @param string $user_id
    * @return UserspecificNotebook|array returns empty array if this gradeable is not a notebook gradeable
    */
    public function getNotebook(string $gradeable_id, string $user_id) {
        if ($this->is_notebook_parsed || !$this->notebook_gradeable) {
            return $this->notebook;
        }
        $notebook_model = new UserSpecificNotebook(
            $this->core,
            $this->notebook,
            $gradeable_id,
            $user_id
        );

        $this->parseNotebook([
            'notebook' => $notebook_model->getNotebookConfig(),
            'testcases' => $notebook_model->getTestCases()
        ]);

        $this->is_notebook_parsed = true;

        //now the autogradingconfig has reparsed the notebook,
        //update the model's representation
        $notebook_model->setNotebookConfig($this->notebook);
        $this->notebook = $notebook_model;

        return $notebook_model;
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
     * Gets the number of inputs on the submission page
     * @return int
     */
    public function getNumInputs() {
        return count($this->getInputs());
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
