<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\FileUtils;
use app\libraries\DateUtils;
use app\libraries\GradeableType;
use app\libraries\Utils;

/**
 * Class Gradeable
 *
 * Model of the current gradeable being looked at for submission by the student. This information is a combination of
 * the info contained in the form json file or database, various result json files, and the version history file in the
 * submission folder. This abstract class is extended by GradeableFile (which loads the form json file) and
 * GradeableDb (which loads the data from the database). Additionally, it'll hold the relevant information necessary
 * for the TA to be able to grade this.
 *
 * @method string getId()
 * @method string getName()
 * @method int getType()
 * @method array getPartNames()
 * @method array getTextboxes()
 * @method int getHighestVersion()
 * @method int getActiveVersion()
 * @method void setActiveVersion(int $version)
 * @method int getMaxSubmissions()
 * @method float getMaxSize()
 * @method GradeableVersion[] getVersions()
 * @method float getNormalPoints() Returns the total number of points for testcases that are not hidden nor are extra credit
 * @method bool setTeamAssignment(bool $team_assignment)
 * @method bool getTeamAssignment()
 * @method int setMaxTeamSize(int $max_team_size)
 * @method int getMaxTeamSize()
 * @method setTeamLockDate(\DateTime $datetime)
 * @method \DateTime getTeamLockDate()
 * @method int getPeerGradeSet()
 * @method void setPeerGradeSet(int $assign)
 * @method bool getPeerGrading()
 * @method void setPeerGrading(bool $peer)
 * @method bool getStudentView()
 * @method void setStudentView(bool $student_view)
 * @method bool getStudentSubmit()
 * @method void setStudentSubmit(bool $student_submit)
 * @method bool getStudentDownload()
 * @method void setStudentDownload(bool $student_download)
 * @method bool getStudentAnyVersion()
 * @method void setStudentAnyVersion(bool $student_any_version)
 * @method setTaViewDate(\DateTime $datetime)
 * @method \DateTime getOpenDate()
 * @method setOpenDate(\DateTime $datetime)
 * @method \DateTime getDueDate()
 * @method \DateTime getGradeStartDate()
 * @method setGradeStartDate(\DateTime $datetime)
 * @method \DateTime getGradeReleasedDate()
 * @method setGradeReleasedDate(\DateTime $datetime)
 * @method bool getGradeByRegistration()
 * @method array getSubmittedFiles()
 * @method array getVcsFiles()
 * @method GradeableTestcase[] getTestcases()
 * @method bool getIsRepository()
 * @method string getSubdirectory()
 * @method string getConfigPath()
 * @method string getGradeFile()
 * @method string getTaInstructions()
 * @method int getInteractiveQueuePosition()
 * @method int getInteractiveQueueTotal()
 * @method int getBatchQueuePosition()
 * @method int getBatchQueueTotal()
 * @method float getPointPrecision()
 * @method User getUser()
 * @method void setUser(User $user)
 * @method Team getTeam()
 * @method GradeableComponent[] getComponents()
 * @method string getOverallComment()
 * @method void setOverallComment(string $comment)
 * @method int getMinimumGradingGroup()
 * @method string getBucket()
 * @method int|null getGdId()
 * @method void setGdId(int $gd_id)
 * @method \DateTime getUserViewedDate()
 * @method float getTotalPeerGradingNonExtraCredit()
 * @method int getLateDayExceptions()
 * @method int getAllowedLateDays()
 * @method int getLateDays()
 */
class Gradeable extends AbstractModel {
    
    /** @property @var string Id of the gradeable (must be unique) */
    protected $id;

    /** @property @var int  */
    protected $gd_id;
    
    /** @property @var string Name of the gradeable */
    protected $name;
    
    /** @property @var int GradeableType set for this Gradeable */
    protected $type;
    
    /** @property @var string Instructions to give to TA for grading */
    protected $ta_instructions = "";
    
    /** @property @var bool Is this a team assignment */
    protected $team_assignment = false;

    /** @property @var int maximum allowed team size */
    protected $max_team_size = 0;

    /** @property @var \DateTime|null Date when students cannot create/leave/join teams without instructor's help */
    protected $team_lock_date = null;
    
    /** @property @var bool Does this assignment use peer grading*/
    protected $peer_grading = false;
    
    /** @property @var int How many people should each person grade*/
    protected $peer_grade_set = 0;
    
    /** @property @var string Rainbow Grades Bucket to place gradeable */
    protected $bucket = null;
    
    /** @property @var int Minimum group that's allowed to submit grades for this gradeable */
    protected $minimum_grading_group = 1;

    /** @property @var \DateTime|null Date for when grading can view */
    protected $ta_view_date = null;

    /** @property @var \DateTime|null Date for when grading can start */
    protected $grade_start_date = null;

    /** @property @var \DateTime|null Date for when the grade will be released to students */
    protected $grade_released_date = null;

    /** @property @var bool */
    protected $ta_grades_released = false;

    /** @property @var bool Should the gradeable be graded by registration section (or by rotating section) */
    protected $grade_by_registration = true;

    /** @property @var \app\models\GradeableComponent[] */
    protected $components = array();

    /* Config variables that are only for electronic submissions */
    /** @property @var bool */
    protected $has_config = false;
    
    /** @property @var \DateTime|null When is an electronic submission open to students */
    protected $open_date = null;

    /** @property @var \DateTime|null Due date for an electronic submission */
    protected $due_date = null;

    /** @property @var bool Is the electronic submission via a VCS repository or by upload */
    protected $is_repository = false;

    /** @property @var string What is the subdirectory for VCS */
    protected $subdirectory = "";

    /** @property @var int Number of days you can submit */
    protected $allowed_late_days = 0;

    /** @property @var string Url to any instructions for the gradeable for students */
    protected $instructions_url = "";

    /** @property @var string Path to the config.json file used to build the config/build/build_XXX.json file */
    protected $config_path = "";

    /** @property @var float Precision to allow for inputting points when grading (such that precision of 0.5 then allows grades
     * of 0, 0.5, 1, 1.5, etc.) */
    protected $point_precision = 0;

    /** @property @var bool Is there any TA grading to be done for this gradeable (ie. any rubric questions) */
    protected $ta_grading = false;

    /** @property @var bool Will students be able to view submissions? */
    protected $student_view = true;
    /** @property @var bool Will students be able to make submissions? */
    protected $student_submit = true;
    /** @property @var bool Will students be able to download submissions? */
    protected $student_download = false;
    /** @property @var bool Will students be able to view/download any version or just the active version? */
    protected $student_any_version = true;

    /* Config variables for submission details for this gradeable */
    /** @property @var float Max size (in bytes) allowed for the submission */
    protected $max_size = 50000;
    /** @property @var int Max number of submission allowed before a student starts suffering deductions every 10 submissions */
    /* NOTE:  This should never be used.  It should always be set in the gradeables build.json file. */
    protected $max_submissions = 20;

    /** @property @var float Non hidden, non extra credit points */
    protected $normal_points = 0;

    /**  @var float Non hidden points (including extra credit) */
    protected $non_hidden_points = 0;

    /** @property @var GradeableTestcase[] Autograding testcases for the gradeable */
    protected $testcases = array();

    /** @property @var string Message to show for the gradeable above all submission results */
    protected $message = "";

    /** @property @var string Message to show when conditions are met */
    protected $incentive_message = "";
    /** @property @var int Minimum days before deadline that a submission must be made by to get the incentive message */
    protected $minimum_days_early = 0;
    /** @property @var int Minimum points that a submission must have to get the incentive message */
    protected $minimum_points = 0;
    /** @property @var int[] test cases that should be summed when determining if student has achieved early submission threshold */
    protected $early_submission_test_cases = array();
    protected $early_total = 0;

    /** @property @var string[] */
    protected $part_names = array();

    /** @property @var string[] */
    protected $textboxes = array();

    /** @property @var int Variables for submission details (such as attempts used, etc.) */
    protected $submissions = 0;

    /** @property @var int $active_version  The set active version for the assignment */
    protected $active_version = -1;
    /** @property @var int $current The current version of the assignment being viewed */
    protected $current_version = -1;
    /** @property @var int $highest Highest version submitted for an assignment */
    protected $highest_version = 0;

    /** @property @var array */
    protected $versions = array();

    /** @property @var array Array of all files for a specified submission number where each key is a previous file
     * and then each element is an array that contains filename, file path, and the file size. */
    protected $submitted_files = array();
    protected $vcs_files = array();
    protected $results_files = array();
    protected $meta_files = array();
    protected $previous_files = array();
    /** @property @var Array of all split pdfsuploads. Each key is a filename and then each element is an array
    * that contains filename, file path, and the file size. */
    protected $uploads_files = array();

    protected $result_details;

    /** @property @var string */
    protected $grade_file = null;

    protected $in_interactive_queue = false;
    protected $grading_interactive_queue = false;
    protected $in_batch_queue = false;
    protected $grading_batch_queue = false;

    /** @property @var string */
    protected $overall_comment = "";

    /** @property @var int */
    protected $interactive_queue_total = 0;
    /** @property @var int */
    protected $interactive_queue_position = 0;
    /** @property @var int */
    protected $batch_queue_total = 0;
    /** @property @var int */
    protected $batch_queue_position = 0;
    /** @property @var int */
    protected $grading_total = 0;

    protected $been_autograded = false;

    protected $total_auto_non_hidden_non_extra_credit = 0;
    protected $total_auto_non_hidden_extra_credit = 0;
    protected $total_auto_hidden_non_extra_credit = 0;
    protected $total_auto_hidden_extra_credit = 0;

    protected $graded_auto_non_hidden_non_extra_credit = 0;
    protected $graded_auto_non_hidden_extra_credit = 0;
    protected $graded_auto_hidden_non_extra_credit = 0;
    protected $graded_auto_hidden_extra_credit = 0;
    protected $submission_time = null;

    protected $been_tagraded = false;

    protected $total_tagrading_non_extra_credit = 0;
    protected $total_tagrading_extra_credit = 0;
    
    protected $total_peer_grading_non_extra_credit = 0;
    protected $total_peer_grading_extra_credit=0;

    /** @property @var \app\models\User|null */
    protected $user = null;

    /** @property @var \app\models\Team|null */
    protected $team = null;

    protected $user_viewed_date = null;

    /** @property @var string The worker pc that this gradeable is to be run on.*/
    protected $required_capabilities = "default";

    /** @property @var int.*/
    protected $max_possible_grading_time = -1;

    /** @property @var int */
    protected $late_day_exceptions = 0;

    /** @property @var int */
    protected $late_days = 0;

    public function __construct(Core $core, $details=array(), User $user = null) {
        parent::__construct($core);
        if(!isset($details['g_id'])) {
            return;
        }
        $this->id = $details['g_id'];

        $this->user = ($user === null) ? $this->core->getUser() : $user;

        if (isset($details['gd_id'])) {
            $this->gd_id = $details['gd_id'];
            $this->overall_comment = $details['gd_overall_comment'];
        }

        $timezone = $this->core->getConfig()->getTimezone();
        $this->name = $details['g_title'];

        $this->ta_instructions = $details['g_overall_ta_instructions'];
        $this->instructions_url = $details['g_instructions_url'];

        $this->type = $details['g_gradeable_type'];
        if ($this->type === GradeableType::ELECTRONIC_FILE) {
            $this->open_date = new \DateTime($details['eg_submission_open_date'], $timezone);
            $this->due_date = new \DateTime($details['eg_submission_due_date'], $timezone);
            $this->allowed_late_days = $details['eg_late_days'];
            $this->is_repository = $details['eg_is_repository'] === true;
            $this->subdirectory = $details['eg_subdirectory'];
            $this->point_precision = floatval($details['eg_precision']);
            $this->ta_grading = $details['eg_use_ta_grading'] === true;
            $this->student_view = $details['eg_student_view'] === true;
            $this->student_submit = $details['eg_student_submit'] === true;
            $this->student_download = $details['eg_student_download'] === true;
            $this->student_any_version = $details['eg_student_any_version'] === true;
            $this->peer_grading = isset($details['eg_peer_grading']) ? $details['eg_peer_grading'] === true: false;
            $this->peer_grade_set = (isset($details['eg_peer_grade_set']) && $this->peer_grading) ? $details['eg_peer_grade_set']: 0;
            $this->config_path = $details['eg_config_path'];
            $this->team_assignment = isset($details['eg_team_assignment']) ? $details['eg_team_assignment'] === true : false;
            $this->max_team_size = $details['eg_max_team_size'];
            $this->team_lock_date = new \DateTime($details['eg_team_lock_date'], $timezone);
            if ($this->team_assignment) {
                $this->team = $this->core->getQueries()->getTeamByGradeableAndUser($this->id, $this->user->getId());
            }
            if (isset($details['active_version']) && $details['active_version'] !== null) {
                $this->been_autograded = true;
                $this->active_version = $details['active_version'];
                $this->graded_auto_non_hidden_non_extra_credit = floatval($details['autograding_non_hidden_non_extra_credit']);
                $this->graded_auto_non_hidden_extra_credit = floatval($details['autograding_non_hidden_extra_credit']);
                $this->graded_auto_hidden_non_extra_credit = floatval($details['autograding_hidden_non_extra_credit']);
                $this->graded_auto_hidden_extra_credit = floatval($details['autograding_hidden_extra_credit']);
                $this->submission_time = new \DateTime($details['submission_time'], $timezone);
                $this->late_day_exceptions = $details['late_day_exceptions'];
                $this->late_days = $details['days_late'];
            }
            
            if (isset($details['highest_version']) && $details['highest_version']!== null) {
               $this->highest_version = $details['highest_version']; 
            }
            $this->loadGradeableConfig();
        }

        if (isset($details['array_gcd_gc_id'])) {
            $this->been_tagraded = true;
            $this->user_viewed_date = $details['gd_user_viewed_date'];
        }

        if (isset($details['array_gc_id'])) {
            $component_fields = array('gc_id', 'gc_title', 'gc_ta_comment', 'gc_student_comment', 'gc_lower_clamp',
                                      'gc_default', 'gc_max_value', 'gc_upper_clamp', 'gc_is_peer', 'gc_is_text', 'gc_order', 'gc_page', 'array_gcm_id', 
                                      'array_gc_id', 'array_gcm_points', 'array_gcm_note', 'array_gcm_publish', 'array_gcm_order');
            $user_fields = array('user_id', 'anon_id', 'user_firstname', 'user_preferred_firstname', 'user_lastname',
                                 'user_email', 'user_group');

            for ($i = 0; $i < count($details['array_gc_id']); $i++) {
                $component_details = array();
                foreach ($component_fields as $key) {
                    if (isset($details["array_{$key}"][$i])) {
                        $component_details[$key] = $details["array_{$key}"][$i];
                    }             
                }


                $grade_details = array();
                $keys = array();
                if (isset($details['array_gcd_gc_id'])) {
                    for ($j = 0; $j < count($details['array_gcd_gc_id']); $j++) {
                        if ($details['array_gcd_gc_id'][$j] === $component_details['gc_id']) {
                            $keys[] = $j;
                            $grade_details[$j] = array();
                            $grade_details[$j]['array_gcm_mark'] = $details['array_array_gcm_mark'][$j];
                            $grade_details[$j]['gcd_score'] = $details['array_gcd_score'][$j];
                            $grade_details[$j]['gcd_component_comment'] = $details['array_gcd_component_comment'][$j];
                            $grade_details[$j]['gcd_graded_version'] = $details['array_gcd_graded_version'][$j];                            
                            $grade_details[$j]['gcd_grade_time'] = $details['array_gcd_grade_time'][$j];

                            if (isset($details['array_gcd_user_id'][$j])) {
                                $user_details = array();
                                foreach ($user_fields as $key) {
                                    $user_details[$key] = $details["array_gcd_{$key}"][$j];
                                }
                                $grade_details[$j]['gcd_grader'] = $this->core->loadModel(User::class, $user_details);
                            }
                        }
                    }
                }
                // really only care about $grade_details (aka component data) if the component is graded should be in the if above probably
                // No need to make two cases here.
                if(count($grade_details) <= 1) {
                    if(count($grade_details) == 1) {
                        $component_details = array_merge($component_details, $grade_details[$keys[0]]);
                    }
                    if($component_details['gc_is_peer']) {
                        $this->components[$component_details['gc_order']] = array($this->core->loadModel(GradeableComponent::class, $component_details));
                        $component_for_info = $this->components[$component_details['gc_order']][0];
                    }
                    else {
                        $this->components[$component_details['gc_order']] = $this->core->loadModel(GradeableComponent::class, $component_details);
                        $component_for_info = $this->components[$component_details['gc_order']];
                    }
                }
                else {
                    $this->components[$component_details['gc_order']] = array();
                    foreach($keys as $key) {
                        $use_this = array_merge($component_details, $grade_details[$key]);
                        $this->components[$component_details['gc_order']][] = $this->core->loadModel(GradeableComponent::class, $use_this);
                    }
                    $component_for_info = $this->components[$component_details['gc_order']][0];
                }

                if (!$component_for_info->getIsText()) {
                    $max_value = $component_for_info->getMaxValue();
                    $this->total_tagrading_non_extra_credit += $max_value;
                }
            }
            // We don't sort by order within the DB as we're aggregating the component details into an array so we'd
            // either write an inner JOIN on that aggregation to order stuff, and then have it aggregated, or we can
            // just order it here, which is simpler in the long run and not really a performance problem.
            ksort($this->components);
            // NOTE: the TA grading total may be negative!
        }

        $this->minimum_grading_group = $details['g_min_grading_group'];
        $this->grade_by_registration = $details['g_grade_by_registration'] === true;
        $this->grade_start_date = new \DateTime($details['g_grade_start_date'], $timezone);
        $this->grade_released_date = new \DateTime($details['g_grade_released_date'], $timezone);
        $this->ta_view_date = new \DateTime($details['g_ta_view_start_date'], $timezone);
        // Is it past when the TA grades should be released
        $this->ta_grades_released = $this->grade_released_date < new \DateTime("now", $timezone);
        $this->bucket = $details['g_syllabus_bucket'];
    }

    /**
     * Loads the config/build/build_*.json file for a gradeable
     */
    protected function loadGradeableConfig() {
        if ($this->type !== GradeableType::ELECTRONIC_FILE) {
            return;
        }

        $details = GradeableAutogradingConfig::getConfig($this->core, $this->getId());

        // Was there actually a config file to read from
        if ($details === false) {
            return;
        }

        $this->has_config = true;

        if (isset($details['max_submission_size'])) {
            $this->max_size = floatval($details['max_submission_size']);
        }

        if (isset($details['max_submissions'])) {
            $this->max_submissions = intval($details['max_submissions']);
        }

        if (isset($details['assignment_message'])) {
            $this->message = Utils::prepareHtmlString($details['assignment_message']);
        }

        if (isset($details['early_submission_incentive'])) {
            $this->incentive_message = Utils::prepareHtmlString($details['early_submission_incentive']['message']);
            $this->minimum_days_early = intval($details['early_submission_incentive']['minimum_days_early']);
            $this->minimum_points = intval($details['early_submission_incentive']['minimum_points']);
            $this->early_submission_test_cases = $details['early_submission_incentive']['test_cases'];
        }

        $num_parts = 1;
        if (isset($details['part_names'])) {
          $num_parts = count($details['part_names']);
        }

        $num_textboxes = 0;
        if (isset($details['textboxes'])) {
          $num_textboxes = count($details['textboxes']);
        }

        $this->required_capabilities = $details['required_capabilities'] ?? 'default';

        $this->max_possible_grading_time = $details['max_possible_grading_time'] ?? -1;

        for ($i = 1; $i <= $num_parts; $i++) {
            $this->previous_files[$i] = array();
            $j = $i - 1;
            if (isset($details['part_names']) && isset($details['part_names'][$j]) &&
                trim($details['part_names'][$j]) !== "") {
                $this->part_names[$i] = $details['part_names'][$j];
            }
            else {
                $this->part_names[$i] = "Part ".$i;
            }
        }

        for ($i = 0; $i < $num_textboxes; $i++) {
          $this->textboxes[$i] = $details['textboxes'][$i];
          // if(isset($detailes['']))
        }

        if (isset($details['testcases'])) {
            foreach ($details['testcases'] as $idx => $testcase) {
                $testcase = $this->core->loadModel(GradeableTestcase::class, $testcase, $idx);
                $this->testcases[] = $testcase;
                if ($testcase->getPoints() > 0) {
                    if ($testcase->isHidden() && $testcase->isExtraCredit()) {
                        $this->total_auto_hidden_extra_credit += $testcase->getPoints();
                    }
                    else if ($testcase->isHidden() && !$testcase->isExtraCredit()) {
                        $this->total_auto_hidden_non_extra_credit += $testcase->getPoints();
                    }
                    else if (!$testcase->isHidden() && $testcase->isExtraCredit()) {
                        $this->total_auto_non_hidden_extra_credit += $testcase->getPoints();
                    }
                    else {
                        $this->total_auto_non_hidden_non_extra_credit += $testcase->getPoints();
                    }
                }

                if ($testcase->getNonHiddenNonExtraCreditPoints() >= 0) {
                  $this->normal_points += $testcase->getNonHiddenNonExtraCreditPoints();
                }
                if ($testcase->getNonHiddenPoints() >= 0) {
                  $this->non_hidden_points += $testcase->getNonHiddenPoints();
                }
            }
        }
    }

    /**
     * Sets the grading queue status of the gradeable. We don't really care
     */
    public function setQueueStatus() {
        $interactive_queue = $this->core->getConfig()->getSubmittyPath()."/to_be_graded_queue";

        // FIXME: batch queue has gone away!
        $batch_queue = $this->core->getConfig()->getSubmittyPath()."/to_be_graded_batch";

        $user_id = $this->user->getId();
        if ($this->team_assignment) {
            $team = $this->core->getQueries()->getTeamByGradeableAndUser($this->id, $user_id);
            if ($team !== null) {
                $user_id = $team->getId();
            }
        }

        $queue_file = implode("__", array($this->core->getConfig()->getSemester(),
                                          $this->core->getConfig()->getCourse(), $this->id,
                                          $user_id, $this->current_version));
        $grade_file = "GRADING_".$queue_file;

        $this->in_interactive_queue = file_exists($interactive_queue."/".$queue_file);
        $this->in_batch_queue = file_exists($batch_queue."/".$queue_file);
        $this->grading_interactive_queue = file_exists($interactive_queue."/".$grade_file);
        $this->grading_batch_queue = file_exists($batch_queue."/".$grade_file);

        $queue_count = 0;
        $grading_count = 0;
        if($this->in_interactive_queue === true) {
            $files = scandir($interactive_queue);
            $f = array();
            $times = array();
            foreach($files as $file) {
              if(is_file($interactive_queue.'/'.$file) && ($file !== "..") && ($file !== ".") && !in_array($file, $f)) {
                  $f[] = $file;
                  $times[] = filemtime($interactive_queue.'/'.$file);
              }
            }
            array_multisort($times,SORT_DESC,$f); //Sorted By Descending Here

            foreach($f as $file) {
                if(is_file($interactive_queue.'/'.$file) && ($file !== "..") && ($file !== ".")) {
                    if(strpos($file, "GRADING_") !== false) {
                        $grading_count = $grading_count + 1;
                    }
                    else {
                        $queue_count = $queue_count + 1;
                        if($file === $queue_file) {
                            $this->interactive_queue_position = $queue_count;
                        }
                    }
                }
            }

            /* Note:  Once permissions to access batch queue from interactive queue has been sorted, then can add in
                      the code below to count the full total of submissions being graded across both queues */
            /*$files = @scandir($batch_queue);
            // Count the number being graded in the batch queue to get total of submissions currently being graded
            foreach($files as $file) {
                if(strpos($file, "GRADING_") !== false) {
                    $grading_count = $grading_count + 1;
                }
            }*/

            $this->interactive_queue_total = $queue_count;
            $this->grading_total = $grading_count;
        }
        else if($this->in_batch_queue === true) {
            $files = scandir($batch_queue);
            $f = array();
            $times = array();
            foreach($files as $file){
              if(is_file($batch_queue.'/'.$file)){
                $f[] = $file;
                $times[] = filemtime($batch_queue.'/'.$file);
              }
            }
            array_multisort($times,SORT_DESC,$f); //Sort By Descending Here

            foreach($f as $file) {
                if(strpos($file, "GRADING_") !== false) {
                    $grading_count = $grading_count + 1;
                }
                else {
                    $queue_count = $queue_count + 1;
                    if($file === $queue_file) {
                        $this->batch_queue_position = $queue_count;
                    }
                }
            }

            /* Note:  Once permissions to access interactive queue from batch queue has been sorted, then can add in
                      the code below to count the full total of submissions being graded across both queues */
            /* $files = @scandir($interactive_queue);
            // Count the number being graded in the batch queue to get total of submissions currently being graded
            foreach($files as $file) {
                if(strpos($file, "GRADING_") !== false) {
                    $grading_count = $grading_count + 1;
                }
            }*/
            $this->batch_queue_total = $queue_count;
            $this->grading_total = $grading_count;
        }
        if($this->in_interactive_queue === false && $this->in_batch_queue === false) {
            $this->interactive_queue_position = 0;
            $this->interactive_queue_total = 0;
            $this->batch_queue_position = 0;
            $this->batch_queue_total = 0;
            $this->grading_total = 0;
        }
    }

    /**
     * Loads submission details about an electronic submission from the submissions/,
     * results/, and uploads/ directories and their respective json files.
     */
    public function loadResultDetails() {
        if ($this->type !== GradeableType::ELECTRONIC_FILE) {
            return;
        }

        if (!$this->hasConfig()) {
            return;
        }

        $user_id = $this->user->getId();
        if ($this->team_assignment) {
            $team = $this->core->getQueries()->getTeamByGradeableAndUser($this->id, $user_id);
            if ($team !== null) {
                $user_id = $team->getId();
            }
            $this->versions = $this->core->getQueries()->getGradeableVersions($this->id, null, $user_id, $this->getDueDate());
        }
        else {
            $this->versions = $this->core->getQueries()->getGradeableVersions($this->id, $user_id, null, $this->getDueDate());
        }
        

        $course_path = $this->core->getConfig()->getCoursePath();

        $submission_path = $course_path."/submissions/".$this->id."/".$user_id;
        $vcs_path = $course_path."/checkout/".$this->id."/".$user_id;
        $results_path = $course_path."/results/".$this->id."/".$user_id;
        $uploads_path = $course_path."/uploads/split_pdf/".$this->id;

        //$this->components = $this->core->getQueries()->getGradeableComponents($this->id, $this->gd_id);

        $this->submissions = count($this->versions);

        if (isset($_REQUEST['gradeable_version'])) {
            $this->current_version = intval($_REQUEST['gradeable_version']);
        }

        if ($this->current_version < 0 && $this->active_version >= 0) {
            $this->current_version = $this->active_version;
        }
        else if ($this->current_version > $this->submissions) {
            $this->current_version = $this->active_version;
        }
        else if (!isset($this->versions[$this->current_version]) && $this->active_version > 0) {
            $this->current_version = $this->active_version;
        }

        $this->setQueueStatus();

        $submission_current_path = $submission_path."/".$this->current_version;
        $submitted_files = FileUtils::getAllFiles($submission_current_path, array(), true);
        foreach ($submitted_files as $file => $details) {
            if (substr(basename($file), 0, 1) === '.') {
                $this->meta_files[$file] = $details;
            }
            else {
                $this->submitted_files[$file] = $details;
            }
        }

        $vcs_current_path = $vcs_path."/".$this->current_version;
        $vcs_files = FileUtils::getAllFiles($vcs_current_path, array(), true);
        foreach ($vcs_files as $file => $details) {
            $this->vcs_files[$file] = $details;
        }

        $results_current_path = FileUtils::joinPaths($results_path,$this->current_version);
        $results_files = FileUtils::getAllFiles($results_current_path, array(), true);
        foreach ($results_files as $file => $details) {
            $this->results_files[$file] = $details;
        }

        $uploads_current_path = $uploads_path;
        $uploads_files = FileUtils::getAllFiles($uploads_current_path);
        foreach ($uploads_files as $timestamp => $content) {
            $this->uploads_files[$timestamp] = $content;
        }

        if ($this->getNumParts() > 1) {
            for ($i = 1; $i <= $this->getNumParts(); $i++) {
                $this->previous_files[$i] = array();
                foreach ($this->submitted_files as $file => $details) {
                    if (substr($file, 0, strlen("part{$i}/")) === "part{$i}/") {
                        $this->previous_files[$i][$file] = $details;
                    }
                }
            }
        }
        else {
            $this->previous_files[1] = $this->submitted_files;
        }

        if ($this->current_version > 0) {
            $this->result_details = FileUtils::readJsonFile(FileUtils::joinPaths($results_path, $this->current_version, "results.json"));
            if ($this->result_details !== false) {
                $history = FileUtils::readJsonFile(FileUtils::joinPaths($results_path, $this->current_version, "history.json"));
                if ($history !== false) {
                    $last_results_timestamp = $history[count($history) - 1];
                } else {
                    $last_results_timestamp = array('submission_time' => "UNKNOWN", "grade_time" => "UNKOWN",
                        "wait_time" => "UNKNOWN");
                }
                $this->result_details = array_merge($this->result_details, $last_results_timestamp);
                $this->result_details['num_autogrades'] = count($history);
                $this->early_total = 0;
                for ($i = 0; $i < count($this->result_details['testcases']); $i++) {
                    $this->testcases[$i]->addResultTestcase($this->result_details['testcases'][$i], FileUtils::joinPaths($results_path, $this->current_version));
                    $pts = $this->testcases[$i]->getPointsAwarded();
                    if ( in_array ($i+1,$this->early_submission_test_cases) ) {
                        $this->early_total += $pts;
                    }
                }
            }
        }

        $grade_file = $this->core->getConfig()->getCoursePath()."/reports/".$this->getId()."/".$user_id.".txt";
        if (is_file($grade_file)) {
            $this->grade_file = htmlentities(file_get_contents($grade_file));
        }
    }

    public function isTeamAssignment() {
        return $this->team_assignment;
    }

    public function getNumParts() {
      return count($this->part_names);
    }

    public function getNumTextBoxes() {
      return count($this->textboxes);
    }

    public function getCurrentVersionNumber() {
        return $this->current_version;
    }

    /**
     * @return GradeableVersion|null
     */
    public function getCurrentVersion() {
        if (!isset($this->versions[$this->current_version])) {
            return null;
        }
        return $this->versions[$this->current_version];
    }

    public function getPreviousFiles($part = 1) {
        $part = ($this->getNumParts() < $part || $part < 1) ? 1 : $part;
        return $this->previous_files[$part];
    }

    public function getSubmissionCount() {
        return $this->submissions;
    }
    
    public function hasSubmitted() {
        return $this->getHighestVersion() > 0;
    }

    public function getTotalNonHiddenNonExtraCreditPoints() {
        return $this->total_auto_non_hidden_non_extra_credit;
    }

    public function getGradedNonHiddenPoints() {
        return $this->graded_auto_non_hidden_extra_credit + $this->graded_auto_non_hidden_non_extra_credit;
    }

    public function getGradedAutograderPoints() {
        return $this->graded_auto_non_hidden_extra_credit +
            $this->graded_auto_non_hidden_non_extra_credit +
            $this->graded_auto_hidden_extra_credit +
            $this->graded_auto_hidden_non_extra_credit;
    }

    public function getTotalAutograderNonExtraCreditPoints() {
        return $this->total_auto_hidden_non_extra_credit + $this->total_auto_non_hidden_non_extra_credit;
    }

    // this is a misnomer now that peers also grade stuff and this accounts for that
    // this is only to be used to show total points to a specific user...this is not their final grade
    public function getGradedTAPoints() {
        $points = 0;
        foreach($this->components as $component) {
            if(is_array($component)) {
                foreach($component as $cmpt) {
                    // if no peers have graded this component or this peer graded this component
                    if((!$cmpt->getHasMarks() && !$cmpt->getHasGrade()) || ($cmpt->getGrader()->getId() == $this->core->getUser()->getId())) {
                        $points += $cmpt->getGradedTAPoints();
                        break;
                    }
                }

            }
            else {
                $points += $component->getGradedTAPoints();
            }
        }
        return $points;
    }

    public function getTotalTANonExtraCreditPoints() {
        return $this->total_tagrading_non_extra_credit;
    }

    public function getTAViewDate(){
        return $this->ta_view_date;
    }

    public function getDaysLate() {
        return ($this->hasResults()) ? $this->getCurrentVersion()->getDaysLate() : 0;
    }

    public function getInstructionsURL(){
        return $this->instructions_url;
    }

    /**
     * Check to see if we have the result_details array from the results directory.
     * If false, we don't want to display any result details to the user about the
     * version.
     *
     * @return bool
     */
    public function hasResults() {
        return isset($this->result_details);
    }

    public function getResults() {
        return $this->result_details;
    }

    public function hasAssignmentMessage() {
        return trim($this->message) !== "";
    }

    public function getAssignmentMessage() {
        return $this->message;
    }

    public function hasIncentiveMessage() {
        return trim($this->incentive_message) !== "";
    }

    public function useVcsCheckout() {
        return $this->is_repository;
    }

    public function beenAutograded() {
        return $this->been_autograded;
    }

    public function beenTAgraded() {
        return $this->been_tagraded;
    }

    public function hasGradeFile() {
        return $this->grade_file !== null;
    }

    public function useTAGrading() {
        return $this->ta_grading;
    }

    public function taGradesReleased() {
        return $this->ta_grades_released;
    }

    public function hasConfig() {
        return $this->has_config;
    }

    public function inInteractiveQueue() {
        return $this->in_interactive_queue;
    }

    public function beingGradedInteractiveQueue() {
        return $this->grading_interactive_queue;
    }

    public function inBatchQueue() {
        return $this->in_batch_queue;
    }

    public function beingGradedBatchQueue() {
        return $this->grading_batch_queue;
    }

    public function getNumberOfGradingTotal() {
        return $this->grading_total;
    }

    public function isGradeByRegistration() {
        return $this->grade_by_registration;
    }

    public function updateUserViewedDate() {
        $this->core->getQueries()->updateUserViewedDate($this);
    }

    public function updateGradeable() {
        $this->core->getQueries()->updateGradeable($this);
    }

    public function getActiveDaysLate() {
        $extended_due_date = clone $this->due_date;
        $return =  DateUtils::calculateDayDiff($extended_due_date->add(new \DateInterval("PT5M")), $this->submission_time);
        if ($return < 0) {
            $return = 0;
        }
        return $return;
    }
    
    public function validateVersions($active_check = null) {
        //active check is the gradeable version this gradeable has
        if($active_check === null) {
            $active_check = $this->active_version;
        }
        if($this->peer_grading) {
            foreach($this->components as $cmpt) {
                if(is_array($cmpt)) {
                    foreach($cmpt as $graded_by) {
                        if($graded_by->getGradedVersion() !== $active_check) {
                            return false;
                        }
                    }
                }
                else {
                    if($cmpt->getGradedVersion() !== $active_check) {
                        if($cmpt->getTitle() != "Grading Complete"){
                            return false;
                        }
                    }
                }
            }
            return true;
        }
        else {
            foreach($this->components as $component) {
                if($component->getGradedVersion() !== $active_check) {
                    return false;
                }
            }
            return true;
        }
        
    }

    public function isFullyGraded()
    {
        if($this->peer_grading) {
            foreach($this->components as $cmpt) {
                if(is_array($cmpt)) {
                    foreach($cmpt as $graded_by) {
                        if($graded_by->getGradedVersion() == -1) {
                            return false;
                        }
                    }
                }
                else {
                    if($cmpt->getGradedVersion() == -1) {
                        if($cmpt->getTitle() != "Grading Complete"){
                            return false;
                        }
                    }
                }
            }
            return true;
        }
        else {
            foreach($this->components as $component) {
                if($component->getGradedVersion() == -1) {
                    return false;
                }
            }
            return true;
        }
    }

    //save all the information in this gradeable and the gradeable components. Used in tests and text/numeric where the whole gradeable is saved at the same time rather than by component
    public function saveData() {
        $this->core->getCourseDB()->beginTransaction();
        if ($this->gd_id === null) {
            $this->gd_id = $this->core->getQueries()->insertGradeableData($this);
        }
        elseif ($this->modified) {
            $this->core->getQueries()->updateGradeableData($this);
        }
        foreach ($this->components as $component) {
            if(is_array($component)) {
                foreach($component as $peer_grade) {
                    $peer_grade->saveGradeableComponentData($this->gd_id);
                }
            }
            else {
                $component->saveGradeableComponentData($this->gd_id);
            }
        }
        $this->core->getCourseDB()->commit();
    }

    //save only the information stored in gradeabledata
    public function saveGradeableData() {
        $this->core->getCourseDB()->beginTransaction();
        if ($this->gd_id === null) {
            $this->gd_id = $this->core->getQueries()->insertGradeableData($this);
        }
        elseif ($this->modified) {
            $this->core->getQueries()->updateGradeableData($this);
        }
        $this->core->getCourseDB()->commit();
    }
      
    public function getSyllabusBucket() {
        return $this->bucket;
    }
    
    public function getNumPeerComponents() {
        $count = 0;
        foreach($this->components as $cmpt) {
            if(is_array($cmpt) || $cmpt->getIsPeer()) {
                $count++;
            }
        }
        return $count;
    }
    
    public function getNumTAComponents() {
        if(!$this->peer_grading) {
            return count($this->components);
        }
        $count = 0;
        foreach($this->components as $cmpt) {
            if(!is_array($cmpt)) {
                if(!$cmpt->getIsPeer()) {
                    $count++;
                }
            }
        }
        return $count;
    }
    
    public function getComponentsGradedBy($grader_id) {
        $return = array();
        foreach($this->components as $cmpt) {
            if(is_array($cmpt)) {
                foreach($cmpt as $peer) {
                    if($peer->getGrader() !== null && $peer->getGrader()->getId() == $grader_id) {
                        $return[] = $peer;
                    }
                }
            }
            else if($cmpt->getGrader() !== null && $cmpt->getGrader()->getId() == $grader_id) {
                $return[] = $cmpt;
            }
        }
        return $return;
    }
}
