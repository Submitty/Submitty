<?php

namespace app\models;

use app\libraries\Core;
use app\models\GradeableComponent;


/**
 * Class AdminGradeable
 * @method void setRotatingGradeables()
 * @method void setGradeableSectionHistory()
 * @method void setNumSections()
 * @method void setGradersAllSection()
 * @method void setGradersFromUserTypes()
 * @method void setTemplateList()
 * @method void setInheritTeamsList();
 * @method \app\models\GradeableComponent[] getOldComponents();
 */
class AdminGradeable extends AbstractModel {

    /* All properties that aren't in the 'gradeable' and 'electronic_gradeable' tables */
    /** @property @var */
    protected $rotating_gradeables = array();
    /** @property @var */
    protected $gradeable_section_history = array();
    /** @property @var */
    protected $num_sections = 0;
    /** @property @var */
    protected $graders_all_section = array();
    /** @property @var */
    protected $graders_from_usertypes = array();
    /** @property @var */
    protected $template_list =  array();
    /** @property @var */
    //protected $inherit_teams_list = array();

    /** @property @var \app\models\GradeableComponent[] */
    protected $old_components;
    /** @property @var same info as above but encoded */
    protected $old_components_json = "{}";

    protected $has_grades = false;
    protected $default_late_days;
    protected $vcs_base_url;


    /** @property @var string Id of the gradeable (must be unique) */
    public $g_id = "";
    /** @property @var string Title of the gradeable */
    public $g_title = "";
    /** @property @var string instructions url of the gradeable */
    public $g_instructions_url = "";
    /** @property @var string Instructions to give to TA for grading */
    public $g_overall_ta_instructions = "";
    /** @property @var int 0 is electronic, 1 is checkponts, 2 is numeric/text */
    public $g_gradeable_type = 0;
    /** @property @var bool Should the gradeable be graded by registration section (or by rotating section) */
    public $g_grade_by_registration = true;
    /** @property @var \DateTime Date for when grading can view */
    public $g_ta_view_start_date;
    /** @property @var \DateTime Date for when grading can start */
    public $g_grade_start_date;
    /** @property @var \DateTime Date for when the grade will be released to students */
    public $g_grade_released_date;
    /** @property @var int Minimum group that's allowed to submit grades for this gradeable */
    public $g_min_grading_group = 1;
    /** @property @var string Iris Bucket to place gradeable */
    public $g_syllabus_bucket = -1;

// if a gradeable is electronic

    /** @property @var string Path to the config.json file used to build the config/build/build_XXX.json file */
    public $eg_config_path = "";
    /** @property @var bool Is the electronic submission via a VCS repository or by upload */
    public $eg_is_repository = false;
    /** @property @var string What is the subdirectory for VCS */
    public $eg_subdirectory = "";
    /** @property @var bool Is this a team assignment */
    public $eg_team_assignment = false;
    /** @property @var string The gradeable to inherit teams from */
    //public $eg_inherit_teams_from = "";
    /** @property @var int maximum allowed team size, Minimum must be 2*/
    public $eg_max_team_size = 2;
    /** @property @var \DateTime Date when students cannot create/leave/join teams without instructor's help */
    public $eg_team_lock_date;
    /** @property @var bool Is there any TA grading to be done for this gradeable (ie. any rubric questions) */
    public $eg_use_ta_grading = false;
    /** @property @var bool Will students be able to view submissions? */
    public $eg_student_view = true;
    /** @property @var bool Will students be able to make submissions? */
    public $eg_student_submit = true;
    /** @property @var bool Will students be able to download submissions? */
    public $eg_student_download = false;
    /** @property @var bool Will students be able to view/download any version or just the active version? */
    public $eg_student_any_version = true;
    /** @property @var bool Does this assignment use peer grading*/
    public $eg_peer_grading = false;
    /** @property @var \DateTime When is an electronic submission open to students */
    public $eg_submission_open_date;
    /** @property @var \DateTime Due date for an electronic submission */
    public $eg_submission_due_date;
    /** @property @var int Number of days late you can submit */
    public $eg_late_days = 2;
    /** @property @var int How many people should each person grade*/
    public $eg_peer_grade_set = 3;
    /** @property @var int How many points for completing peer grading*/
    public $eg_peer_grade_complete_score = 0;
    /** @property @var float Precision to allow for inputting points when grading (such that precision of 0.5 then allows grades of 0, 0.5, 1, 1.5, etc.) */
    public $eg_precision = 0.5;
    /** @property @var bool is there a pdf page*/
    public $eg_pdf_page = false;
    /** @property @var bool does the student supply the pdf page*/
    public $eg_pdf_page_student = false;

// if a gradeable is numeric/text

    public $num_numeric = 0;
    public $num_text = 0;

    public function __construct(Core $core) {
        parent::__construct($core);
        $tonight = new \DateTime();
        $tonight->setTime(23, 59, 59);

        $this->g_ta_view_start_date = (clone $tonight)->sub(new \DateInterval('P1D'));
        $this->g_grade_start_date = (clone $tonight)->add(new \DateInterval('P10D'));
        $this->g_grade_released_date = (clone $tonight)->add(new \DateInterval('P14D'));
        $this->eg_team_lock_date = (clone $tonight)->add(new \DateInterval('P7D'));
        $this->eg_submission_open_date = (clone $tonight);
        $this->eg_submission_due_date = (clone $tonight)->add(new \DateInterval('P7D'));
        $this->default_late_days = $this->core->getConfig()->getDefaultHwLateDays();
        $this->vcs_base_url = $this->core->getConfig()->getVcsBaseUrl();
        $this->old_components = array(new GradeableComponent($this->core, array()));
    }

    // following only if edit or from template
    public function setGradeableInfo($details=array(), $template) {
        $this->g_id = $details['g_id'];
        $this->g_title = $details['g_title'];
        $this->g_instructions_url = $details['g_instructions_url'];
        $this->g_overall_ta_instructions = $details['g_overall_ta_instructions'];
        $this->g_gradeable_type = $details['g_gradeable_type'];
        $this->g_grade_by_registration = $details['g_grade_by_registration'];
        if(!$template){
            $this->g_ta_view_start_date = new \DateTime($details['g_ta_view_start_date']);
            $this->g_grade_start_date = new \DateTime($details['g_grade_start_date']);
            $this->g_grade_released_date = new \DateTime($details['g_grade_released_date']);
        }
        $this->g_min_grading_group = $details['g_min_grading_group'];
        $this->g_syllabus_bucket = $details['g_syllabus_bucket'];        
    }

    // public function addGradeableComponent($details=array()) {
    //     $this->old_components[] = new GradeableComponent($this->core, $details);
    // }

    public function setElectronicGradeableInfo($details, $template) {
        $this->eg_config_path = $details['eg_config_path'];
        $this->eg_is_repository = $details['eg_is_repository'];
        $this->eg_subdirectory = $details['eg_subdirectory'];
        $this->eg_team_assignment = $details['eg_team_assignment'];
        //$this->eg_inherit_teams_from = $details['eg_inherit_teams_from'];
        $this->eg_max_team_size = $details['eg_max_team_size'];
        if(!$template) {
            $this->eg_team_lock_date = new \DateTime($details['eg_team_lock_date']);
        }
        $this->eg_use_ta_grading = $details['eg_use_ta_grading'];
        $this->eg_student_view = $details['eg_student_view'];
        $this->eg_student_submit = $details['eg_student_submit'];
        $this->eg_student_download = $details['eg_student_download'];
        $this->eg_student_any_version = $details['eg_student_any_version'];
        $this->eg_peer_grading = $details['eg_peer_grading'];
        if(!$template) {
            $this->eg_submission_open_date = new \DateTime($details['eg_submission_open_date']);
            $this->eg_submission_due_date = new \DateTime($details['eg_submission_due_date']);
        }
        $this->eg_late_days = $details['eg_late_days'];
        $this->eg_peer_grade_set = $details['eg_peer_grade_set'];
        $this->eg_precision = $details['eg_precision'];
    }

    public function setNumericTextInfo($details) {
        $this->num_text = $details['num_text'];
        $this->num_numeric = $details['num_numeric'];
    }

    public function setHasGrades($has_grades) {
        $this->has_grades = $has_grades;
    }
}
