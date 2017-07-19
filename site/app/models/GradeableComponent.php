<?php

namespace app\models;
use app\libraries\Core;

/**
 * Class GradeableComponent
 *
 * Model for the GradeableComponent which is a join of the gradeable_component and gradeable_component_data
 * tables for a particular student's gradeable. As we're doing a join with the _data table, the gcd_*
 * fields can be null if this particular gradeable has not yet been graded so we have to give them default
 * values so that we're not propagating nulls around.
 *
 * The gradeable can either have a max score that is either positive or negative. If it's positive, we
 * can clamp the earned score between 0 and the max score, and if it's negative, we clamp from the
 * max score to 0.
 *
 * @method int getId()
 * @method int getGdId()
 * @method void setGdId(int $id)
 * @method string getTitle()
 * @method string getTaComment()
 * @method string getStudentComment()
 * @method float getMaxValue()
 * @method bool getIsText();
 * @method bool getIsExtraCredit()
 * @method int getOrder()
 * @method float getScore()
 * @method setScore(float $score)
 * @method string getComment()
 * @method void setComment(string $comment)
 * @method User getGrader()
 * @method void setGrader(User $grader)
 * @method int getGradedVersion()
 * @method void setGradedVersion(int $graded_version)
 * @method \DateTime getGradeTime()
 * @method void setGradeTime(\DateTime $date_time)
 * @method bool getHasGrade()
 * @method int getGradedVersion()
 */
class GradeableComponent extends AbstractModel {
    /** @property @var int Unique identifier for the component */
    protected $id = null;
    /** @property @var string Title of the component shown to students and graders */
    protected $title = "";
    /** @property @var string Comment shown to graders during grading about this particular component */
    protected $ta_comment = "";
    /** @property @var string Comment shown to both graders and students giving more information about the component */
    protected $student_comment = "";
    /** @property @var float Maximum value that the component can have */
    protected $max_value = 0;
    /** @property @var bool Is the component just used for text fields (ignore max_value and is_extra_credit and score) */
    protected $is_text = false;
    /** @property @var bool Is the component extra credit for this gradeable */
    protected $is_extra_credit = false;
    /** @property @var int Order for components to be shown in */
    protected $order = 1;
    /** @property @var float Given grade that someone has given this component */
    protected $score = 0;
    /** @property @var string Comment that grader has put on the component while grading for student */
    protected $comment = "";

    /** @property @var User */
    protected $grader = null;

    /** @property @var int */
    protected $graded_version = -1;

    /** @property @var \DateTime */
    protected $grade_time = null;

    /** @property @var bool */
    protected $has_grade = false;

    /** @property @var bool Does this component use peer grading*/
    protected $peer_grading = false;

    /** @property @var \app\models\GradeableComponentMark[] */
    protected $marks = array();

    public function __construct(Core $core, $details=array()) {
        parent::__construct($core);
        if (!isset($details['gc_id'])) {
            return;
        }
        $this->id = $details['gc_id'];
        $this->title = $details['gc_title'];
        $this->ta_comment = $details['gc_ta_comment'];
        $this->student_comment = $details['gc_student_comment'];
        $this->max_value = $details['gc_max_value'];
        $this->is_text = $details['gc_is_text'];
        $this->is_extra_credit = $details['gc_is_extra_credit'];
        $this->order = $details['gc_order'];
        
        if (isset($details['gcd_score']) && $details['gcd_score'] !== null) {
            $this->has_grade = true;
            $this->grader = $details['gcd_grader'];
            $this->graded_version = isset($details['gcd_graded_version']) ? $details['gcd_graded_version']: null;
            if (isset($details['gcd_grade_time'])) {
                $this->grade_time = new \DateTime($details['gcd_grade_time'], $this->core->getConfig()->getTimezone());
            }
            // will need to edit this to clarify this is only personalized score
            // will need to add a total score
            $this->score = floatval($details['gcd_score']);
            if (!$this->is_text) {
                if ($this->max_value > 0) {
                    if ($this->max_value < $this->score) {
                        $this->score = $this->max_value;
                    }
                    elseif ($this->score < 0) {
                        $this->score = 0;
                    }
                }
                else {
                    if ($this->max_value > $this->score) {
                        $this->score = $this->max_value;
                    }
                    elseif ($this->score > 0) {
                        $this->score = 0;
                    }
                }
            }
            $this->comment = $details['gcd_component_comment'];
            if ($this->comment === null) {
                $this->comment = "";
            }
        }

        if (isset($details['array_gcm_id'])) {

            $mark_fields = array('gcm_id', 'gc_id', 'gcm_points',
                                    'gcm_note', 'gcm_order');
            foreach ($mark_fields as $key) {
                $details["array_{$key}"] = explode(',', $details["array_{$key}"]);
            }

            for ($i = 0; $i < count($details['array_gcm_id']); $i++) {
                $mark_details = array();
                foreach ($mark_fields as $key) {
                    $mark_details[$key] = $details["array_{$key}"][$i];
                }

                // see if this actually works...
                if (isset($details['array_gcmd_gcm_id'])) {
                    for ($j = 0; $j < count($details['array_gcmd_gcm_id']); $j++) {
                        if ($details['array_gcmd_gcm_id'][$j] === $mark_details['gcm_id']) {
                            $mark_details['gcm_has_mark'] = true;
                            break;
                        }
                    }
                }

                $this->marks[$mark_details['gcm_order']] = $this->core->loadModel(GradeableComponentMark::class, $mark_details);

            }

            ksort($this->marks);
        }

    }

    /**
     * @raises \BadMethodCallException
     */
    public function setId() {
        throw new \BadMethodCallException('Call to undefined method '.__CLASS__.'::setId()');
    }

    public function saveData($gd_id) {
        if ($this->modified) {
            if ($this->has_grade) {
                $this->core->getQueries()->updateGradeableComponentData($gd_id, $this);
            }
            else {
                $this->core->getQueries()->insertGradeableComponentData($gd_id, $this);
            }
        }

        foreach ($this->marks as $mark) {
            $mark->saveData($gd_id, $this->id);
        }
    }
}
