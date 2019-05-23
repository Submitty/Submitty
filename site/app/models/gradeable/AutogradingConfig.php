<?php

namespace app\models\gradeable;


use app\exceptions\FileNotFoundException;
use app\exceptions\IOException;
use app\libraries\Core;
use app\libraries\FileUtils;
use app\libraries\Utils;
use app\models\AbstractModel;
use app\models\grading\AbstractGradingInput;
use app\models\GradeableTestcase;
use app\models\gradeable\AutogradingTestcase;

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
 */
class AutogradingConfig extends AbstractModel {

    /** @property @var int The maximum allowed size (in bytes) of a submission */
    protected $max_submission_size;
    /** @property @var int The maximum number of submissions allowed */
    protected $max_submissions;
    /** @property @var string A message to show the user above the file upload box */
    protected $gradeable_message;

    /** @property @var string Any additional requirements for worker machine (i.e. "extra_ram")  */
    protected $required_capabilities;
    /** @property @var int The number of seconds allowed for autograding */
    protected $max_possible_grading_time = -1;

    /** @property @var string[] The names of different upload bins on the submission page (1-indexed) */
    protected $part_names = [];

    /** @property @var array Array of notebook objects */
    private $notebook = [];
    /** @property @var AbstractGradingInput[] Grading input configs for all new types of gradeable input*/
    private $inputs = [];
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

    /** @property @var int Total number of non-hidden non-extra-credit ('normal') points for all test cases */
    protected $total_non_hidden_non_extra_credit = 0;
    /** @property @var int Total number of non-hidden extra-credit points for all test cases */
    protected $total_non_hidden_extra_credit = 0;
    /** @property @var int Total number of hidden non-extra-credit points for all test cases */
    protected $total_hidden_non_extra_credit = 0;
    /** @property @var int Total number of hidden extra-credit points for all test cases */
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
            $this->gradeable_message = Utils::prepareHtmlString($details['assignment_message'] ?? '');
        } else if (isset($details['gradeable_message'])) {
            $this->gradeable_message = Utils::prepareHtmlString($details['gradeable_message'] ?? '');
        }

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
        $num_inputs = 0;
        $temp_count = 0;
        $other_count = 0;
        $inner_loop_count = 0;
        $actual_input = array();
        if (isset($details['notebook'])) {
            foreach ($details['notebook'] as $notebook_item) {

                $num_inputs = $num_inputs + count($notebook_item['input'] ?? []);
                foreach ($notebook_item['input'] as $inp) {

                    // Add field to notebook input object which contains the content of the prev submission
//                    $prev_submission = $this->getPrevSubmissionContents($inp['filename']);
                    $prev_submission = "test";

                    // If no previous submission populate this filed with the starter_value_string
                    if($prev_submission === NULL)
                    {
                        $inp['prev_submission_string'] = $prev_submission;

                    } else {

                        // Else use the prev submission string
                        $inp['prev_submission_string'] = $prev_submission;
                    }

                    $notebook_item['input'][$inner_loop_count] = $inp;

                    $actual_input[$temp_count] = $inp;
                    $temp_count++;
                    $inner_loop_count++;
                }

                // Reset inner loop counter
                $inner_loop_count = 0;

                $this->notebook[$other_count] = $notebook_item;
                $other_count++;
            }
        }

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

        // Get the input details
        for ($i = 0; $i < $num_inputs; $i++) {
            if ($actual_input[$i]['type'] == "short_answer") {
                $this->inputs[$i] = new SubmissionTextBox($this->core, $actual_input[$i]);
            } elseif ($actual_input[$i]['type'] == "codebox") {
                $this->inputs[$i] = new SubmissionCodeBox($this->core, $actual_input[$i]);
            } elseif ($actual_input[$i]['type'] == "multiplechoice") {
                $this->inputs[$i] = new SubmissionMultipleChoice($this->core, $actual_input[$i]);
            }
        }
    }

    private function getPrevSubmissionContents($filename) {

        // Get items in path to student's submission folder
        $course_path = $this->core->getConfig()->getCoursePath();
        $gradable_dir = $_GET['gradeable_id'];
        $student_id = $this->core->getUser()->getId();

        // Join path items
        $user_submission_folder = FileUtils::joinPaths($course_path, 'submissions', $gradable_dir, $student_id);

        // Get some info about the files in this user's submission folder
        $files = FileUtils::getAllFiles($user_submission_folder);

        // Get number of submissions
        // This is equal to number of files in directory minus 1 (to account for user json file inside directory)
        $num_of_submissions = count($files) - 1;

        // If no submissions yet return null
        if($num_of_submissions <= 0)
        {
            return NULL;
        }

        // Append submission folder to $user_submission_folder
        $user_submission_folder = FileUtils::joinPaths($user_submission_folder, $num_of_submissions);

        // Get complete file path
        $complete_file_path = FileUtils::joinPaths($user_submission_folder, $filename);

        // If desired file does not exist in the most recent submission directory throw exception
        if(!file_exists($complete_file_path))
        {
            throw new FileNotFoundException("Unable to locate submission file.");
        }

        // Read file contents into string
        $file_contents = file_get_contents($complete_file_path);

        // If file_contents is False an error has occured
        if($file_contents === False)
        {
            throw new IOException("An error retrieving submission contents.");
        }

        // Remove trailing newline
        $file_contents = rtrim($file_contents, "\n");

        // Get the contents of the most recent submission and return it
        return $file_contents;
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

    public function getNotebook() {
        return $this->notebook;
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
