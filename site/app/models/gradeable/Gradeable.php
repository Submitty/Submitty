<?php

namespace app\models\gradeable;

use app\libraries\GradeableType;
use app\exceptions\AggregateException;
use app\exceptions\NotImplementedException;

/**
 * All data describing the configuration of a gradeable
 *  Note: All per-student data is in the (TODO) class
 *
 *  Note: there is no guarantee of the values of properties not relevant to the gradeable type
 *
 *  Missing validation: Date validation, student permissions
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
 * @method void setStudentAnyVersion($student_any_version);
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
class Gradeable
{
    /* Properties for all types of gradeables */

    /** @var string The course-wide unique gradeable id */
    protected $id = "";
    /** @var string The gradeable's title */
    protected $title = "";
    /** @var string The instructions url to give to students */
    protected $instructions_url = "";
    /** @var int The type of gradeable */
    protected $type = GradeableType::ELECTRONIC_FILE;
    /** @var bool If the gradeable should be graded per registration section (true) or rotating sections(false) */
    protected $grade_by_registration = true;
    /** @var \DateTime The so-called 'TA Beta-Testing' date.  This is when the gradeable appears for TA's */
    protected $ta_view_start_date = null;
    /** @var \DateTime The date that graders may start grading */
    protected $grade_start_date = null;
    /** @var \DateTime The date that grades will be released to students */
    protected $grade_released_date = null;
    /** @var \DateTime The date after which only instructors may change grades (aka when grades are 'due') */
    protected $grade_locked_date = null;
    /** @var int The minimum user group that can grade this gradeable (1=instructor) */
    protected $min_grading_group = 1;
    /** @var string The syllabus classification of this gradeable */
    protected $syllabus_bucket = "Homework";

    /** @var Component[] An array of all of this gradeable's components */
    protected $components = array();

    /* Properties exclusive to numeric-text/checkpoint gradeables */

    /** @var string The overall ta instructions for grading (numeric-text/checkpoint only) */
    protected $ta_instructions = "";

    /* Properties exclusive to electronic gradeables */

    /** @var string The location of the autograding configuration file */
    protected $autograding_config_path = "";
    /** @var string[] The object that contains the autograding config data */
    private $autograding_config = array();
    /** @var bool If the gradeable is using vcs upload (true) or manual upload (false) */
    protected $vcs = false;
    /** @var string The subdirectory within the VCS repository for this gradeable */
    protected $vcs_subdirectory = "";
    /** @var bool If the gradeable is a team assignment */
    protected $team_assignment = false;
    /** @var int The maximum team size (if the gradeable is a team assignment) */
    protected $team_size_max = 0;
    /** @var \DateTime The deadline for joining teams (if the gradeable is a team assignment) */
    protected $team_lock_date = null;
    /** @var bool If the gradeable is using any manual grading */
    protected $ta_grading = false;
    /** @var bool If students can view submissions */
    protected $student_view = false;
    /** @var bool If students can make submissions */
    protected $student_submit = false;
    /** @var bool If students can download submitted files */
    protected $student_download = false;
    /** @var bool If students can view/download any version of the submitted files, or just the active version */
    protected $student_any_version = false;
    /** @var bool If the gradeable uses peer grading */
    protected $peer_grading = false;
    /** @var int The number of peers each student will be graded by */
    protected $peer_grade_set = 0;
    /** @var \DateTime The date students can start making submissions */
    protected $submission_open_date = null;
    /** @var \DateTime The date, before which all students must make a submissions (or be marked late) */
    protected $submission_due_date = null;
    /** @var int The number of late days allowed */
    protected $late_days = 0;
    /** @var bool If submission after student's max deadline
     *      (due date + min(late days allowed, late days remaining)) is allowed
     */
    protected $late_submission_allowed = true;
    /** @var float The point precision for manual grading */
    protected $precision = 0.0;

    public function __construct($data, array $components)
    {
        $this->setId($data["id"]);
        $this->setTitle($data["title"]);
        $this->setInstructionsUrl($data["instructions_url"]);
        $this->setType($data["type"]);
        $this->setGradeByRegistration($data["grade_by_registration"]);
        $this->setMinGradingGroup($data["min_grading_group"]);
        $this->setSyllabusBucket($data["syllabus_bucket"]);
        $this->setComponents($components);

        if($this->getType() === GradeableType::ELECTRONIC_FILE) {
            $this->setTaInstructions($data["ta_instructions"]);
            $this->setAutogradingConfigPath($data["autograding_config_path"]);
            $this->setVcs($data["vcs"]);
            $this->setVcsSubdirectory($data["vcs_subdirectory"]);
            $this->setTeamAssignment($data["team_assignment"]);
            $this->setTeamSizeMax($data["team_size_max"]);
            $this->setTaGrading($data["ta_grading"]);
            $this->setStudentView($data["student_view"]);
            $this->setStudentSubmit($data["student_submit"]);
            $this->setStudentDownload($data["student_download"]);
            $this->setStudentAnyVersion($data["student_any_version"]);
            $this->setPeerGrading($data["peer_grading"]);
            $this->setPeerGradeSet($data["peer_grade_set"]);
            $this->setLateSubmissionAllowed($data["late_submission_allowed"]);
            $this->setPrecision($data["precision"]);
        }

        // Set dates last
        $this->setDates($data["ta_view_start_date"], $data["team_lock_date"], $data["submission_open_date"],
            $data["submission_due_date"], $data["grade_start_date"], $data["grade_released_date"],
            $data["grade_locked_date"], $data["late_days"]);
    }

    public function getAutogradingConfig()
    {
        return $this->autograding_config;
    }


    /* Overridden setters with validation */

    /**
     * Validates the state of the gradeable's dates
     *
     * @return string[] | null the error message(s)
     */
    private function validateDates(\DateTime $ta_view_start_date, \DateTime $team_lock_date, \DateTime $submission_open_date,
                                   \DateTime $submission_due_date, \DateTime $grade_start_date, \DateTime $grade_released_date,
                                   \DateTime $grade_locked_date, $late_days)
    {
        return null;
    }

    public function setDates(\DateTime $ta_view_start_date, \DateTime $team_lock_date, \DateTime $submission_open_date,
                             \DateTime $submission_due_date, \DateTime $grade_start_date, \DateTime $grade_released_date,
                             \DateTime $grade_locked_date, $late_days)
    {
        $errors = $this->validateDates($ta_view_start_date, $team_lock_date, $submission_open_date, $submission_due_date,
            $grade_start_date, $grade_released_date, $grade_locked_date, $late_days);

        if ($errors !== null) {
            throw new AggregateException("Date validation failed!", $errors);
        }

        $this->ta_view_start_date = $ta_view_start_date;
        $this->grade_start_date = $grade_start_date;
        $this->grade_released_date = $grade_released_date;
        $this->grade_locked_date = $grade_locked_date;

        if($this->type === GradeableType::ELECTRONIC_FILE) {
            if($this->team_assignment) {
                $this->team_lock_date = $team_lock_date;
            }
            $this->submission_open_date = $submission_open_date;
            $this->submission_due_date = $submission_due_date;
            $this->late_days = $late_days;
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
            throw new \InvalidArgumentException("Grading group must be a natural number");
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
        //TODO: recreate the config object
    }

    private function setTeamAssignment($use_teams)
    {
        $this->team_assignment = $use_teams === true;
    }
}