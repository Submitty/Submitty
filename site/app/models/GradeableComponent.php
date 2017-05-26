<?php

namespace app\models;

/**
 * Class GradeableComponent
 *
 * Model for the GradeableComponent which is a join of the gradeable_component and gradeable_component_data
 * tables for a particular student's gradeable. As we're doing a join with the _data table, the gcd_*
 * fields can be null if this particular gradeable has not yet been graded so we have to give them default
 * values so that we're not propagating nulls around
 */
class GradeableComponent extends AbstractModel {
    /** @var int Unique identifier for the component */
    protected $id;
    /** @var string Title of the component shown to students and graders */
    protected $title;
    /** @var string Comment shown to graders during grading about this particular component */
    protected $ta_comment;
    /** @var string Comment shown to both graders and students giving more information about the component */
    protected $student_comment;
    /** @var float Maximum value that the component can have */
    protected $max_value;
    /** @var bool Is the component just used for text fields (ignore max_value and is_extra_credit and score) */
    protected $is_text;
    /** @var bool Is the component extra credit for this gradeable */
    protected $is_extra_credit;
    /** @var int Order for components to be shown in */
    protected $order;
    /** @var float Given grade that someone has given this component */
    protected $score = 0;
    /** @var string Comment that grader has put on the component while grading for student */
    protected $comment = "";

    protected $graded = false;

    public function __construct($details) {
        $this->id = $details['gc_id'];
        $this->title = $details['gc_title'];
        $this->ta_comment = $details['gc_ta_comment'];
        $this->student_comment = $details['gc_student_comment'];
        $this->max_value = $details['gc_max_value'];
        $this->is_text = $details['gc_is_text'];
        $this->is_extra_credit = $details['gc_is_extra_credit'];
        $this->order = $details['gc_order'];
        if (isset($details['gcd_score']) && $details['gcd_score'] !== null) {
            $this->graded = true;
            $this->score = floatval($details['gcd_score']);
            if (!$this->is_text) {
              if ($this->max_value > 0) {
                if ($this->max_value < $this->score) {
                  $this->score = $this->max_value;
                } if ($this->score < 0) {
                  $this->score = 0;
                }
              } else {
                // it's a penalty (negative) item
              }
            }
            $this->comment = $details['gcd_component_comment'];
            if ($this->comment === null) {
                $this->comment = "";
            }
        }
    }

    public function getId() {
        return $this->id;
    }

    public function getTitle() {
        return $this->title;
    }

    public function getTAComment() {
        return $this->ta_comment;
    }

    public function getStudentComment() {
        return $this->student_comment;
    }

    public function getMaxValue() {
        return $this->max_value;
    }

    public function isText() {
        return $this->is_text;
    }

    public function isExtraCredit() {
        return $this->is_extra_credit;
    }

    public function getOrder() {
        return $this->order;
    }

    public function getScore() {
        return $this->score;
    }

    public function setScore($score) {
        $this->score = floatval($score);
    }

    public function getComment() {
        return $this->comment;
    }

    public function hasGrade() {
        return $this->graded;
    }
}