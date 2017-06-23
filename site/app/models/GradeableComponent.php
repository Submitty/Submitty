<?php

namespace app\models;

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
 * @method bool getHasGrade()
 */
class GradeableComponent extends AbstractModel {
    /** @property @var int Unique identifier for the component */
    protected $id;
    /** @property @var string Title of the component shown to students and graders */
    protected $title;
    /** @property @var string Comment shown to graders during grading about this particular component */
    protected $ta_comment;
    /** @property @var string Comment shown to both graders and students giving more information about the component */
    protected $student_comment;
    /** @property @var float Maximum value that the component can have */
    protected $max_value;
    /** @property @var bool Is the component just used for text fields (ignore max_value and is_extra_credit and score) */
    protected $is_text;
    /** @property @var bool Is the component extra credit for this gradeable */
    protected $is_extra_credit;
    /** @property @var int Order for components to be shown in */
    protected $order;
    /** @property @var float Given grade that someone has given this component */
    protected $score = 0;
    /** @property @var string Comment that grader has put on the component while grading for student */
    protected $comment = "";

    /** @property @var bool */
    protected $has_grade = false;

    public function __construct($details) {
        parent::__construct();
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
    }
}
