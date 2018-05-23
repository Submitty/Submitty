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
 * @method float getLowerClamp()
 * @method void setLowerClamp(float $lower_clamp)
 * @method float getDefault()
 * @method void setDefault(float $default)
 * @method float getMaxValue()
 * @method void setMaxValue(float $max_value)
 * @method float getUpperClamp()
 * @method void setUpperClamp(float$upper_clamp)
 * @method bool getIsText();
 * @method bool getIsExtraCredit()
 * @method bool getIsPeer()
 * @method void setIsPeer(bool $peer_grading)
 * @method int getOrder()
 * @method float getScore()
 * @method setScore(float $score)
 * @method string getComment()
 * @method void setComment(string $comment)
 * @method User getGrader()
 * @method int getGradedVersion()
 * @method void setGradedVersion(int $graded_version)
 * @method \DateTime getGradeTime()
 * @method void setGradeTime(\DateTime $date_time)
 * @method bool getHasGrade()
 * @method int getPage()
 * @method bool getHasMarks()
 * @method GradeableComponentMark[] getMarks()
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
    /** @property @var float Minimum value that the component can have */
    protected $lower_clamp = 0;
    /** @property @var float Value that the component starts grading at */
    protected $default = 0;
    /** @property @var float Value that the component is worth */
    protected $max_value = 0;
    /** @property @var float Maximum value that the component can have */
    protected $upper_clamp = 0;
    /** @property @var bool Is the component just used for text fields (ignore lower_clamp, default, max_value, upper_clamp and score) */
    protected $is_text = false;
    /** @property @var int Order for components to be shown in */
    protected $order = 0;
    /** @property @var float custom "mark" score for this component */
    protected $score = 0;
    /** @property @var string Comment that grader has put on the custom "mark" while grading for student */
    protected $comment = "";
    /** @property @var int for page number */
    protected $page = 0;

    /** @property @var User */
    protected $grader = null;

    /** @property @var int */
    protected $graded_version = -1;

    /** @property @var \DateTime */
    protected $grade_time = null;

    /** @property @var bool */
    protected $has_grade = false;

    /** @property @var bool */
    protected $has_marks = false;

    /** @property @var bool Does this component use peer grading*/
    protected $is_peer = false;

    /** @property @var \app\models\GradeableComponentMark[] */
    protected $marks = array();

    /** @property @var bool has the grader of this component been modified*/
    protected $grader_modified = false;

    public function __construct(Core $core, $details=array()) {
        parent::__construct($core);
        if (!isset($details['gc_id'])) {
            return;
        }
        $this->id = $details['gc_id'];
        $this->title = $details['gc_title'];
        $this->ta_comment = $details['gc_ta_comment'];
        $this->student_comment = $details['gc_student_comment'];
        $this->lower_clamp = $details['gc_lower_clamp'];
        $this->default = $details['gc_default'];
        $this->max_value = $details['gc_max_value'];
        $this->upper_clamp = $details['gc_upper_clamp'];
        $this->is_text = $details['gc_is_text'];
        $this->order = $details['gc_order'];
        $this->is_peer = isset($details['gc_is_peer']) ? $details['gc_is_peer']: false;
        $this->page = $details['gc_page'];
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
            $this->comment = $details['gcd_component_comment'];
            $this->score = floatval($details['gcd_score']);
            if ($this->comment === null) {
                $this->comment = "";
            }
        }

        if (isset($details['array_gcm_id'])) {
            $mark_fields = array('gcm_id', 'gc_id', 'gcm_points', 'gcm_publish',
                                    'gcm_note', 'gcm_order');
            foreach ($mark_fields as $key) {
                $details["array_{$key}"] = explode(',', $details["array_{$key}"]);
            }

            if (isset($details['array_gcm_mark'])) {
                $details['array_gcm_mark'] = explode(',', $details['array_gcm_mark']);
            }
            for ($i = 0; $i < count($details['array_gcm_id']); $i++) {
                $mark_details = array();
                foreach ($mark_fields as $key) {
                    $mark_details[$key] = $details["array_{$key}"][$i];
                }
                if (isset($details['array_gcm_mark']) && $details['array_gcm_mark'] !== null) {
                    $this->has_marks = true;
                    for ($j = 0; $j < count($details['array_gcm_mark']); $j++) {
                        if ($details['array_gcm_mark'][$j] === $mark_details['gcm_id']) {
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

    public function getGradedTAPoints() {
        $points = $this->default;
        foreach ($this->marks as $mark) {
            if ($mark->getHasMark()) {
                $points += $mark->getPoints();
            }
        }
        $points += $this->score;
        return min(max($points, $this->lower_clamp), $this->upper_clamp);
    }

    public function getGradedTAPrecisionValue($gradeable) {
      $point_precision = $gradeable->getPointPrecision();
      $str_point_precision = (string) $point_precision;
      $num_decimals =  strlen(substr(strrchr($str_point_precision, "."), 1));

      $str1 = '%4.';
      $str2 = 'f';
      $precision_str = $str1 . (string) $num_decimals . $str2;
      return $precision_str;
    }

    public function getGradedTAComments($nl, $show_students, $gradeable, $use_ascii = true) {
        $text = "";
        $first_text = true;
        if(!$use_ascii){
            $checkedBox = '<i class="fa fa-check-square-o fa-1g"></i> ';
            $box = '<i class="fa fa-square-o"></i> ';
        }else{
            $checkedBox = '(*)';
            $box = '( )';
        }
        foreach ($this->marks as $mark) {
            $points_string = "    ";
            if ($mark->getPoints() != 0) {
              $points_string = sprintf($this->getGradedTAPrecisionValue($gradeable), $mark->getPoints());
            }
            $hasmark = $box;
            if($mark->getHasMark() === true) {
              $hasmark = $checkedBox;
            } else if (!($show_students === true && $mark->getPublish() === 't')) {
              continue;
            }
            $newline=$nl;
            if ($first_text === true) {
              $newline = "";
              $first_text = false;
            }
            $text .= $newline . $hasmark . $points_string . "  " . $mark->getNote();
        }
        if($this->score != 0 || $this->comment != "") {
            $newline=$nl;
            if ($first_text === true) {
              $newline = "";
              $first_text = false;
            }
            $score_string = "    ";
            if (floatval($this->score) != 0) {
                $score_string = sprintf($this->getGradedTAPrecisionValue($gradeable), $this->score);
            }
            $text .= $newline . $checkedBox . $score_string . "  " . $this->comment;
        }
        return $text;
    }

    public function setGrader(User $user) {
        if($this->grader !== null && $this->grader->getId() !== $user->getId()) {
            $this->grader_modified = true;
            $this->modified = true;
        }
        $this->grader = $user;
    }

    /**
     * @raises \BadMethodCallException
     */
    public function setId() {
        throw new \BadMethodCallException('Call to undefined method '.__CLASS__.'::setId()');
    }

    public function deleteData($gd_id) {
        $this->core->getQueries()->deleteGradeableComponentData($gd_id, $this->core->getUser()->getId(), $this);
    }

    public function saveGradeableComponentData($gd_id) {
        if ($this->modified) {
            if ($this->has_grade || $this->has_marks) {
                $this->core->getQueries()->updateGradeableComponentData($gd_id, $this->getGrader()->getId(), $this);
            }
            else {
                $this->core->getQueries()->insertGradeableComponentData($gd_id, $this);
            }
        }
    }

    public function setMarks($marks) {
        if(count($this->marks) == 0) {
            $this->marks = $marks;
        }
    }

}
