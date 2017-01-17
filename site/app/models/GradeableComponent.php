<?php

namespace app\models;

/**
 * Class GradeableComponent
 *
 * Model for the GradeableComponent data whether read from a file or from the database (to mimic
 * the behavior of the main Gradeable model)
 */
class GradeableComponent {
    private $id;
    private $title;
    private $ta_comment;
    private $student_comment;
    private $max_value;
    private $is_text;
    private $is_extra_credit;
    private $order;
    private $score;
    private $comment;

    public function __construct($details) {
        $this->id = $details['gc_id'];
        $this->title = $details['gc_title'];
        $this->ta_comment = $details['gc_ta_comment'];
        $this->student_comment = $details['gc_student_comment'];
        $this->max_value = $details['gc_max_value'];
        $this->is_text = $details['gc_is_text'];
        $this->is_extra_credit = $details['gc_is_extra_credit'];
        $this->order = $details['gc_order'];
        $this->score = $details['gcd_score'];
        $this->comment = $details['gcd_component_comment'];
    }
}