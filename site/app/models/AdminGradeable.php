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
 */
class AdminGradeable extends AbstractModel {
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



    /** @property @var string Id of the gradeable (must be unique) */
    protected $g_id = "";
    /** @property @var string Title of the gradeable */
    protected $g_title = "";    
    /** @property @var string instructions url of the gradeable */
    protected $g_instructions_url = "";
    /** @property @var string Instructions to give to TA for grading */
    protected $g_overall_ta_instructions = "";
    /** @property @var int 0 is electronic, 1 is checkponts, 2 is numeric/text */
    protected $g_gradeable_type = 0;
    /** @property @var bool Should the gradeable be graded by registration section (or by rotating section) */
    protected $g_grade_by_registration = -1;
    /** @property @var \DateTime Date for when grading can view */
    protected $g_ta_view_start_date;
    /** @property @var \DateTime Date for when grading can start */
    protected $g_grade_start_date;
    /** @property @var \DateTime Date for when the grade will be released to students */
    protected $g_grade_released_date;
    /** @property @var int Minimum group that's allowed to submit grades for this gradeable */
    protected $g_min_grading_group = 0;
    /** @property @var string Iris Bucket to place gradeable */
    protected $g_syllabus_bucket = -1;

    /** @property @var \app\models\GradeableComponent[] */
    protected $old_components;
    /** @property @var same info as above but encoded */
    protected $old_components_json = "{}";

    protected $has_grades = false;

// if a gradeable is electronic

    /** @property @var string Path to the config.json file used to build the config/build/build_XXX.json file */
    protected $eg_config_path = "";
    /** @property @var bool Is the electronic submission via a VCS repository or by upload */
    protected $eg_is_repository = false;
    /** @property @var string What is the subdirectory for VCS */
    protected $eg_subdirectory = "";
    /** @property @var bool Is this a team assignment */
    protected $eg_team_assignment = false;
    /** @property @var int maximum allowed team size, Minimum must be 2*/
    protected $eg_max_team_size = 2;
    /** @property @var \DateTime Date when students cannot create/leave/join teams without instructor's help */
    protected $eg_team_lock_date;
    /** @property @var bool Is there any TA grading to be done for this gradeable (ie. any rubric questions) */
    protected $eg_use_ta_grading = false;
    /** @property @var bool Will students be able to view submissions? */
    protected $eg_student_view = true;
    /** @property @var bool Will students be able to make submissions? */
    protected $eg_student_submit = true;
    /** @property @var bool Will students be able to download submissions? */
    protected $eg_student_download = false;
    /** @property @var bool Will students be able to view/download any version or just the active version? */
    protected $eg_student_any_version = true;
    /** @property @var bool Does this assignment use peer grading*/
    protected $eg_peer_grading = false;
    /** @property @var \DateTime When is an electronic submission open to students */
    protected $eg_submission_open_date;
    /** @property @var \DateTime Due date for an electronic submission */
    protected $eg_submission_due_date;
    /** @property @var int Number of days late you can submit */
    protected $eg_late_days = 2;
    /** @property @var int How many people should each person grade*/
    protected $eg_peer_grade_set = 3;
    /** @property @var int How many points for completing peer grading*/
    protected $peer_grade_complete_score = 0;
    /** @property @var float Precision to allow for inputting points when grading (such that precision of 0.5 then allows grades of 0, 0.5, 1, 1.5, etc.) */
    protected $eg_precision = 0.5;
    /** @property @var bool is there a pdf page*/
    protected $pdf_page = false;
    /** @property @var bool does the student supply the pdf page*/
    protected $pdf_page_student = false;

// if a gradeable is numeric/text

    protected $num_numeric = 0;
    protected $num_text = 0;


    protected $default_late_days;
    protected $vcs_base_url;
    protected $BASE_URL = "http:/localhost/hwgrading";

    public function __construct(Core $core) {
        parent::__construct($core);
        $this->g_ta_view_start_date = date('Y-m-d 23:59:59O', strtotime( '-1 days' ));
        $this->g_grade_start_date = date('Y-m-d 23:59:59O', strtotime( '+10 days' ));
        $this->g_grade_released_date = date('Y-m-d 23:59:59O', strtotime( '+14 days' ));
        $this->eg_team_lock_date = date('Y-m-d 23:59:59O', strtotime( '+7 days' ));
        $this->eg_submission_open_date = date('Y-m-d 23:59:59O', strtotime( '0 days' ));
        $this->eg_submission_due_date = date('Y-m-d 23:59:59O', strtotime( '+7 days' ));
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
            $this->g_ta_view_start_date = $details['g_ta_view_start_date'];
            $this->g_grade_start_date = $details['g_grade_start_date'];
            $this->g_grade_released_date = $details['g_grade_released_date'];
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
        $this->eg_max_team_size = $details['eg_max_team_size'];
        if(!$template) {
            $this->eg_team_lock_date = $details['eg_team_lock_date'];
        }
        $this->eg_use_ta_grading = $details['eg_use_ta_grading'];
        $this->eg_student_view = $details['eg_student_view'];
        $this->eg_student_submit = $details['eg_student_submit'];
        $this->eg_student_download = $details['eg_student_download'];
        $this->eg_student_any_version = $details['eg_student_any_version'];
        $this->eg_peer_grading = $details['eg_peer_grading'];
        if(!$template) {
            $this->eg_submission_open_date = $details['eg_submission_open_date'];
            $this->eg_submission_due_date = $details['eg_submission_due_date'];
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
