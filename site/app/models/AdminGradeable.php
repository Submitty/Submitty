<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\DateUtils;
use app\libraries\GradeableType;
use app\models\gradeable\Gradeable;


/**
 * Class AdminGradeable
 *
 * @method array getGradeableSectionHistory()
 * @method int getNumSections()
 * @method array getGradersFromUsertypes()
 * @method string[] getTemplateList()
 * @method bool getHasGrades()
 * @method getDefaultLateDays()
 * @method string getVcsBaseUrl()
 * @method bool getPdfPage()
 * @method bool getPdfPageStudent()
 * @method int getNumNumeric()
 * @method int getNumText()
 * @method Gradeable getGradeable()
 */
class AdminGradeable extends AbstractModel {
    /** @property @var string[][] The rotating section data of all rotating gradeables, indexed by grader id, then gradeable id */
    protected $gradeable_section_history = array();
    /** @property @var int The number of rotating sections */
    protected $num_sections = 0;
    /** @property @var string[] Ids of gradeables with rotating sections */
    protected $rotating_gradeables = array();
    /** @property @var string[][] An array of array of grader names, indexed by user type */
    protected $graders_from_usertypes = array();
    /** @property @var string[] Array of previous grader ids */
    protected $template_list = array();
    /** @property @var string[] Array of previous team gradeables */
    //protected $inherit_teams_list = array();
    /** @property @var bool Whether this gradeable has grades yet */
    protected $has_grades = false;
    /** @property @var int Default late day count for course */
    protected $default_late_days;
    /** @property @var string Course url for vcs */
    protected $vcs_base_url = "";
    /** @property @var bool Is there is a pdf page */
    protected $pdf_page = false;
    /** @property @var bool If the student supplies the pdf page */
    protected $pdf_page_student = false;
    /** @property @var int The number of numeric components (if numeric/text) */
    protected $num_numeric = 0;
    /** @property @var int The number of text components (if numeric/text) */
    protected $num_text = 0;

    /** @property @var Gradeable The gradeable configuration */
    protected $gradeable = null;

    public function __construct(Core $core, Gradeable $gradeable = null) {
        parent::__construct($core);

        $this->gradeable = $gradeable;

        // Construct history array
        $this->graders_from_usertypes = $this->core->getQueries()->getGradersByUserType();
        foreach ($this->graders_from_usertypes as $usertype) {
            foreach ($usertype as $grader) {
                $this->gradeable_section_history[$grader] = [];
            }
        }
        foreach ($this->core->getQueries()->getGradeablesPastAndSection() as $row) {
            $this->gradeable_section_history[$row['user_id']][$row['g_id']] = $row['sections_rotating_id'];

            // Use the keys to remove duplicates
            $this->rotating_gradeables[$row['g_id']] = 1;
        }
        $this->rotating_gradeables = array_keys($this->rotating_gradeables);

        $this->num_sections = $this->core->getQueries()->getNumberRotatingSections();
        $this->template_list = $this->core->getQueries()->getAllGradeablesIdsAndTitles();

        // TODO: Should this be part of the Gradeable class?
        $this->has_grades = false;

        $this->default_late_days = $this->core->getConfig()->getDefaultHwLateDays();
        $this->vcs_base_url = $this->core->getConfig()->getVcsBaseUrl();

        if ($gradeable !== null) {
            if ($gradeable->getType() === GradeableType::NUMERIC_TEXT) {
                // Count text/numeric components if that is the gradeable type
                foreach ($gradeable->getComponents() as $component) {
                    if ($component->isText()) {
                        ++$this->num_text;
                    } else {
                        ++$this->num_numeric;
                    }
                }
            } else if ($gradeable->getType() === GradeableType::ELECTRONIC_FILE) {
                // Get pdf page settings if electronic
                foreach ($gradeable->getComponents() as $component) {
                    if ($component->getPage() !== 0) {
                        $this->pdf_page = true;
                        $this->pdf_page_student = $component->getPage() === -1;
                    }
                    break;
                }
            }
        }
        // $this->inherit_teams_list = $this->core->getQueries()->getAllElectronicGradeablesWithBaseTeams();
    }

    public function getTypeString() {
        return GradeableType::typeToString($this->gradeable->getType());
    }

    /* Intentionally Unimplemented accessor methods */

    public function setRotatingGradeables($rotating_gradeables) {
        throw new \BadFunctionCallException('Setters disabled for AdminGradeable');
    }

    public function setGradeableSectionHistory($gradeable_section_history) {
        throw new \BadFunctionCallException('Setters disabled for AdminGradeable');
    }

    public function setNumSections($num_sections) {
        throw new \BadFunctionCallException('Setters disabled for AdminGradeable');
    }

    public function setGradersAllSections($graders_all_sections) {
        throw new \BadFunctionCallException('Setters disabled for AdminGradeable');
    }

    public function setGradersFromUsertypes($graders_from_usertypes) {
        throw new \BadFunctionCallException('Setters disabled for AdminGradeable');
    }

    public function setTemplateList($template_list) {
        throw new \BadFunctionCallException('Setters disabled for AdminGradeable');
    }

    public function setHasGrades($has_graders) {
        throw new \BadFunctionCallException('Setters disabled for AdminGradeable');
    }

    public function setDefaultLateDays($default_late_days) {
        throw new \BadFunctionCallException('Setters disabled for AdminGradeable');
    }

    public function setVcsBaseUrl($vcs_base_url) {
        throw new \BadFunctionCallException('Setters disabled for AdminGradeable');
    }

    public function setPdfPage($pdf_page) {
        throw new \BadFunctionCallException('Setters disabled for AdminGradeable');
    }

    public function setPdfPageStudent($pdf_page_student) {
        throw new \BadFunctionCallException('Setters disabled for AdminGradeable');
    }

    public function setNumNumeric($num_numeric) {
        throw new \BadFunctionCallException('Setters disabled for AdminGradeable');
    }

    public function setNumText($num_text) {
        throw new \BadFunctionCallException('Setters disabled for AdminGradeable');
    }

    public function setGradeable(Gradeable $gradeable) {
        throw new \BadFunctionCallException('Setters disabled for AdminGradeable');
    }
}

