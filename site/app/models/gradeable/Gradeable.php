<?php

namespace app\models\gradeable;

use app\libraries\DateUtils;
use app\libraries\GradeableType;
use app\exceptions\AggregateException;
use app\exceptions\NotImplementedException;
use app\libraries\Utils;
use app\libraries\FileUtils;
use app\libraries\Core;
use app\models\AbstractModel;

/**
 * All data describing the configuration of a gradeable
 *  Note: All per-student data is in the (TODO) class
 *
 *  Note: there is no guarantee of the values of properties not relevant to the gradeable type
 *
 *  Missing validation: student permissions (i.e. view/submit) - low priority
 *
 * @method string getId();
 * @method string getTitle();
 * @method string getInstructionsUrl();
 * @method void setInstructionsUrl($url);
 * @method int getType();
 * @method bool isGradeByRegistration();
 * @method void setGradeByRegistration($grade_by_reg);
 * @method \DateTime getTaViewStartDate();
 * @method \DateTime getGradeStartDate();
 * @method \DateTime getGradeReleasedDate();
 * @method \DateTime getGradeLockedDate();
 * @method \DateTime getMinGradingGroup();
 * @method string getSyllabusBucket();
 * @method void setSyllabusBucket($bucket);
 * @method Component[] getComponents();
 * @method string getTaInstructions();
 * @method void setTaInstructions($instructions);
 * @method string getAutogradingConfigPath();
 * @method bool isVcs();
 * @method void setVcs($use_vcs);
 * @method string getVcsSubdirectory();
 * @method void setVcsSubdirectory($subdirectory);
 * @method bool isTeamAssignment();
 * @method int getTeamSizeMax();
 * @method void setTeamSizeMax($max_team_size);
 * @method \DateTime getTeamLockDate();
 * @method bool isTaGrading();
 * @method void setTaGrading($use_ta_grading);
 * @method bool isStudentView();
 * @method void setStudentView($can_student_view);
 * @method bool isStudentSubmit();
 * @method void setStudentSubmit($can_student_submit);
 * @method bool isStudentDownload();
 * @method void setStudentDownload($can_student_download);
 * @method bool isStudentAnyVersion();
 * @method void setStudentDownloadAnyVersion($student_download_any_version);
 * @method bool isPeerGrading();
 * @method void setPeerGrading($use_peer_grading);
 * @method int getPeerGradeSet();
 * @method void setPeerGradeSet($grade_set)
 * @method \DateTime getSubmissionOpenDate();
 * @method \DateTime getSubmissionDueDate();
 * @method int getLateDays();
 * @method bool isLateSubmissionAllowed();
 * @method void setLateSubmissionAllowed($allow_late_submission)
 * @method float getPrecision();
 * @method void setPrecision($grading_precision);
 */
class Gradeable extends AbstractModel
{
    /* Properties for all types of gradeables */

    /** @property @var string The course-wide unique gradeable id */
    protected $id = "";
    /** @property @var string The gradeable's title */
    protected $title = "";
    /** @property @var string The instructions url to give to students */
    protected $instructions_url = "";
    /** @property @var int The type of gradeable */
    protected $type = GradeableType::ELECTRONIC_FILE;
    /** @property @var bool If the gradeable should be graded per registration section (true) or rotating sections(false) */
    protected $grade_by_registration = true;
    /** @property @var \DateTime The so-called 'TA Beta-Testing' date.  This is when the gradeable appears for TA's */
    protected $ta_view_start_date = null;
    /** @property @var \DateTime The date that graders may start grading */
    protected $grade_start_date = null;
    /** @property @var \DateTime The date that grades will be released to students */
    protected $grade_released_date = null;
    /** @property @var \DateTime The date after which only instructors may change grades (aka when grades are 'due') */
    protected $grade_locked_date = null;
    /** @property @var int The minimum user group that can grade this gradeable (1=instructor) */
    protected $min_grading_group = 1;
    /** @property @var string The syllabus classification of this gradeable */
    protected $syllabus_bucket = "Homework";

    /** @property @var Component[] An array of all of this gradeable's components */
    protected $components = array();

    /* Properties exclusive to numeric-text/checkpoint gradeables */

    /** @property @var string The overall ta instructions for grading (numeric-text/checkpoint only) */
    protected $ta_instructions = "";

    /* Properties exclusive to electronic gradeables */

    /** @property @var string The location of the autograding configuration file */
    protected $autograding_config_path = "";
    /** @property @var string[] The object that contains the autograding config data */
    private $autograding_config = null;
    /** @property @var bool If the gradeable is using vcs upload (true) or manual upload (false) */
    protected $vcs = false;
    /** @property @var string The subdirectory within the VCS repository for this gradeable */
    protected $vcs_subdirectory = "";
    /** @property @var bool If the gradeable is a team assignment */
    protected $team_assignment = false;
    /** @property @var int The maximum team size (if the gradeable is a team assignment) */
    protected $team_size_max = 0;
    /** @property @var \DateTime The deadline for joining teams (if the gradeable is a team assignment) */
    protected $team_lock_date = null;
    /** @property @var bool If the gradeable is using any manual grading */
    protected $ta_grading = false;
    /** @property @var bool If students can view submissions */
    protected $student_view = false;
    /** @property @var bool If students can make submissions */
    protected $student_submit = false;
    /** @property @var bool If students can download submitted files */
    protected $student_download = false;
    /** @property @var bool If students can view/download any version of the submitted files, or just the active version */
    protected $student_download_any_version = false;
    /** @property @var bool If the gradeable uses peer grading */
    protected $peer_grading = false;
    /** @property @var int The number of peers each student will be graded by */
    protected $peer_grade_set = 0;
    /** @property @var \DateTime The date students can start making submissions */
    protected $submission_open_date = null;
    /** @property @var \DateTime The date, before which all students must make a submissions (or be marked late) */
    protected $submission_due_date = null;
    /** @property @var int The number of late days allowed */
    protected $late_days = 0;
    /** @property @var bool If submission after student's max deadline
     *      (due date + min(late days allowed, late days remaining)) is allowed
     */
    protected $late_submission_allowed = true;
    /** @property @var float The point precision for manual grading */
    protected $precision = 0.0;

    public function __construct(Core $core, $details, array $components)
    {
        parent::__construct($core);

        $this->setId($details["id"]);
        $this->setTitle($details["title"]);
        $this->setInstructionsUrl($details["instructions_url"]);
        $this->setType($details["type"]);
        $this->setGradeByRegistration($details["grade_by_registration"]);
        $this->setMinGradingGroup($details["min_grading_group"]);
        $this->setSyllabusBucket($details["syllabus_bucket"]);
        $this->setComponents($components);

        if($this->getType() === GradeableType::ELECTRONIC_FILE) {
            $this->setTaInstructions($details["ta_instructions"]);
            $this->setAutogradingConfigPath($details["autograding_config_path"]);
            $this->setVcs($details["vcs"]);
            $this->setVcsSubdirectory($details["vcs_subdirectory"]);
            $this->setTeamAssignment($details["team_assignment"]);
            $this->setTeamSizeMax($details["team_size_max"]);
            $this->setTaGrading($details["ta_grading"]);
            $this->setStudentView($details["student_view"]);
            $this->setStudentSubmit($details["student_submit"]);
            $this->setStudentDownload($details["student_download"]);
            $this->setStudentDownloadAnyVersion($details["student_download_any_version"]);
            $this->setPeerGrading($details["peer_grading"]);
            $this->setPeerGradeSet($details["peer_grade_set"]);
            $this->setLateSubmissionAllowed($details["late_submission_allowed"]);
            $this->setPrecision($details["precision"]);
        }

        // Set dates last
        $this->setDates($details);
    }

    const dates = [
        'ta_view_start_date',
        'grade_start_date',
        'grade_released_date',
        'team_lock_date',
        'submission_open_date',
        'submission_due_date',
        'grade_locked_date'
    ];
    public function toArray()
    {
        // Use the default behavior for the most part, but convert the dates
        $return = parent::toArray();

        foreach(self::dates as $date) {
            $return[$date] = $this->$date !== null ? DateUtils::dateTimeToString($this->$date) : null;
        }

        return $return;
    }

    private function loadAutogradingConfig() {
        $course_path = $this->core->getConfig()->getCoursePath();
        $details = FileUtils::readJsonFile(FileUtils::joinPaths($course_path, "config", "build",
            "build_{$this->id}.json"));

        if (isset($details['max_submission_size'])) {
            $details['max_submission_size'] = floatval($details['max_submission_size']);
        }

        if (isset($details['max_submissions'])) {
            $details['max_submissions'] = intval($details['max_submissions']);
        }

        if (isset($details['assignment_message'])) {
            $details['assignment_message'] = Utils::prepareHtmlString($details['assignment_message']);
        }

        return $details;
    }
    public function getAutogradingConfig()
    {
        if($this->type !== GradeableType::ELECTRONIC_FILE) {
            throw new \BadFunctionCallException("Cannot load autograding config for non-electronic file!");
        }

        // use JIT loading
        if ($this->autograding_config === null) {
            $this->autograding_config = $this->loadAutogradingConfig();
        }
        return $this->autograding_config;
    }


    /* Overridden setters with validation */
    private function validateDates(array &$dates)
    {
        $errors = [];

        //
        // Parse all of the dates into DateTime's (no error if null)
        //
        foreach(self::dates as $date) {
            if($dates[$date] === null) continue;
            $result = DateUtils::assertDate($dates[$date], $this->core->getConfig()->getTimezone());
            if ($result !== null) {
                $errors[$date] = $result;
            }
        }

        $ta_view_start_date = $dates['ta_view_start_date'];
        $grade_start_date = $dates['grade_start_date'];
        $grade_released_date = $dates['grade_released_date'];
        $team_lock_date = $dates['team_lock_date'];
        $submission_open_date = $dates['submission_open_date'];
        $submission_due_date = $dates['submission_due_date'];
        $late_days = $dates['late_days'];

        $late_interval = null;
        $late_days = intval($late_days);
        if ($late_days < 0) {
            $errors['late_days'] = 'Late day count must be >= 0!';
        } else {
            try {
                $late_interval = new \DateInterval('P' . strval($late_days) . 'D');
            }
            catch (\Exception $e) {
                // This is for development debugging. In reality, we should never hit this line
                $errors['late_days'] = "Error parsing late days: {$e}";
            }
        }

        $max_due = $submission_due_date;
        if ($submission_due_date instanceof \DateTime && $late_interval !== null) {
            $max_due = (clone $submission_due_date)->add($late_interval);
        }

        if ($ta_view_start_date === null) {
            $errors['ta_view_start_date'] = "Value must not be null!";
        }
        if ($grade_released_date === null) {
            $errors['grade_released_date'] = "Value must not be null!";
        }

        if ($this->type === GradeableType::ELECTRONIC_FILE) {
            if ($submission_open_date === null) {
                $errors['submission_open_date'] = "Value must not be null!";
            }
            if ($submission_due_date === null) {
                $errors['submission_due_date'] = "Value must not be null!";
            }

            if (!($ta_view_start_date === null || $submission_open_date === null) && $ta_view_start_date > $submission_open_date) {
                $errors['g_ta_view_start_date'] = 'TA Beta Testing Date must not be later than Submission Open Date';
            }
            if (!($submission_open_date === null || $submission_due_date === null) && $submission_open_date > $submission_due_date) {
                $errors['eg_submission_open_date'] = 'Submission Open Date must not be later than Submission Due Date';
            }
            if ($this->ta_grading) {
                if ($grade_start_date === null) {
                    $errors['grade_start_date'] = "Value must not be null!";
                }
//                if ($grade_locked_date === null) {
//                    $errors['grade_locked_date'] = "Value must not be null!";
//                }
                if (!($submission_due_date === null || $grade_start_date === null) && $submission_due_date > $grade_start_date) {
                    $errors['g_grade_start_date'] = 'Manual Grading Open Date must be no earlier than Due Date';
                }
                if (!($grade_start_date === null || $grade_released_date === null) && $grade_start_date > $grade_released_date) {
                    $errors['g_grade_released_date'] = 'Grades Released Date must be later than the Manual Grading Open Date';
                }
            } else {
                // No TA grading, but we must set this start date so the database
                //  doesn't complain when we update it
                $grade_start_date = $grade_released_date;
                if (!($max_due === null || $grade_released_date === null) && $max_due > $grade_released_date) {
                    $errors['g_grade_released_date'] = 'Grades Released Date must be later than the Due Date + Max Late Days';
                }
            }
            if ($this->team_assignment) {
                if ($team_lock_date === null) {
                    $errors['team_lock_date'] = "Value must not be null!";
                }
            }
        } else {
            // The only check if its not an electronic gradeable
            if (!($ta_view_start_date === null || $grade_released_date === null) && $ta_view_start_date > $grade_released_date) {
                $errors['g_grade_released_date'] = 'Grades Released Date must be later than the TA Beta Testing Date';
            }
        }

        if (count($errors) === 0)
            return null;
        return $errors;
    }

    public function setDates(array $dates)
    {
        $errors = $this->validateDates($dates);

        if ($errors !== null) {
            throw new AggregateException("Date validation failed!", $errors);
        }

        $this->ta_view_start_date = $dates['ta_view_start_date'];
        $this->grade_start_date = $dates['grade_start_date'];
        $this->grade_released_date = $dates['grade_released_date'];
        $this->grade_locked_date = $dates['grade_locked_date'];

        if($this->type === GradeableType::ELECTRONIC_FILE) {
            if($this->team_assignment) {
                $this->team_lock_date = $dates['team_lock_date'];
            }
            $this->submission_open_date = $dates['submission_open_date'];
            $this->submission_due_date = $dates['submission_due_date'];
            $this->late_days = $dates['late_days'];
        }
    }
    private function setTaViewStartDate($date)
    {
        throw new NotImplementedException('Individual date setters are disabled, use "setDates" instead');
    }
    private function setGradeStartDate($date)
    {
        throw new NotImplementedException('Individual date setters are disabled, use "setDates" instead');
    }
    private function setGradeReleasedDate($date)
    {
        throw new NotImplementedException('Individual date setters are disabled, use "setDates" instead');
    }
    private function setGradeLockedDate($date)
    {
        throw new NotImplementedException('Individual date setters are disabled, use "setDates" instead');
    }
    private function setTeamLockDate($date)
    {
        throw new NotImplementedException('Individual date setters are disabled, use "setDates" instead');
    }
    private function setSubmissionOpenDate($date)
    {
        throw new NotImplementedException('Individual date setters are disabled, use "setDates" instead');
    }
    private function setSubmissionDueDate($date)
    {
        throw new NotImplementedException('Individual date setters are disabled, use "setDates" instead');
    }

    private function setId($id)
    {
        preg_match('/^[a-zA-Z0-9_-]*$/', $id, $matches, PREG_OFFSET_CAPTURE);
        if (count($matches) === 0) {
            throw new \InvalidArgumentException("Gradeable id must be alpha-numeric/hyphen/underscore only");
        }
        $this->id = $id;
    }

    public function setTitle($title)
    {
        if ($title === '') {
            throw new \InvalidArgumentException("Gradeable title must not be blank");
        }
        $this->title = strval($title);
    }

    private function setType($type)
    {
        // Call this to make an exception if the type is invalid
        GradeableType::typeToString($type);
        $this->type = $type;
    }

    public function setMinGradingGroup($group)
    {
        // Disallow the 0 group (this may catch some potential bugs with instructors not being able to edit gradeables)
        if (is_int($group) && $group > 0) {
            $this->min_grading_group = $group;
        } else {
            throw new \InvalidArgumentException("Grading group must be an integer larger than 0");
        }
    }

    public function setComponents(array $components)
    {
        foreach ($components as $component) {
            if (!($component instanceof Component)) {
                throw new \InvalidArgumentException("Object in components array wasn't a component");
            }
        }
        $this->components = $components;
    }

    public function setAutogradingConfigPath($path)
    {
        if ($path === '') {
            throw new \InvalidArgumentException("Autograding configuration file path cannot be blank");
        }
        $this->autograding_config_path = strval($path);
    }

    private function setTeamAssignment($use_teams)
    {
        $this->team_assignment = $use_teams === true;
    }
}