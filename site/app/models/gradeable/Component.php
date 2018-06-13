<?php

namespace app\models\gradeable;


/**
 * Class Component
 * @package app\models\gradeable
 *
 * All data describing the configuration of a Gradeable Component
 *
 * TODO: Missing validation features:
 *  -Lower clamp <= default <= max value <= upper clamp
 *
 * @method getId();
 * @method getTitle();
 * @method setTitle($title);
 * @method getTaComment();
 * @method setTaComment($ta_comment);
 * @method getStudentComment();
 * @method setStudentComment($student_comment);
 * @method getLowerClamp();
 * @method getDefault();
 * @method getMaxValue();
 * @method getUpperClamp();
 * @method isText();
 * @method setText($is_text);
 * @method isPeer();
 * @method setPeer($is_peer);
 * @method getOrder();
 * @method setOrder($order);
 * @method getPage();
 * @method setPage($page);
 */
class Component
{
    /** @var int The course-wide unique numeric id of this component */
    protected $id = 0;
    /** @var string The title of this component */
    protected $title = "";
    /** @var string The comment only visible to the TA/manual grader */
    protected $ta_comment = "";
    /** @var string The comment visible to the student */
    protected $student_comment = "";
    /** @var int The minimum points this component can contribute to the score (can be negative) */
    protected $lower_clamp = 0;
    /** @var int The number of points this component is worth with no marks */
    protected $default = 0;
    /** @var int The full value of this component without extra credit */
    protected $max_value = 0;
    /** @var int The maximum number of points this component can contribute to the score (can be > $max_value) */
    protected $upper_clamp = 0;
    /** @var bool If this is a text component (true) or a numeric component (false) for numeric/text components */
    protected $text = false;
    /** @var bool If this is a peer grading component */
    protected $peer = false;
    /** @var int The order of this component in the gradeable */
    protected $order = -1;
    /** @var int The pdf page this component will reside in */
    protected $page = -1;

    /** @var Mark[] All possible common marks that can be assigned to this component */
    protected $marks = array();


    public function __construct($details, array $marks)
    {
        $this->setMarks($marks);
        $this->setId($details['id']);
        $this->setTitle($details['title']);
        $this->setTaComment($details['ta_comment']);
        $this->setStudentComment($details['student_comment']);
        $this->setLowerClamp($details['lower_clamp']);
        $this->setDefault($details['default']);
        $this->setMaxValue($details['max_value']);
        $this->setUpperClamp($details['upper_clamp']);
        $this->setText($details['text']);
        $this->setPeer($details['peer']);
        $this->setOrder($details['order']);
        $this->setPage($details['page']);
    }

    /* Overridden setters with validation */

    public function setMarks(array $marks)
    {
        // Make sure we're getting only marks
        foreach ($marks as $mark) {
            if (!($mark instanceof Mark)) {
                throw new \InvalidArgumentException("Object in marks array wasn't a mark");
            }
        }
        $this->marks = $marks;
    }

    public function setId($id)
    {
        if (is_int($id) && $id >= 0) {
            $this->id = $id;
        } else {
            throw new \InvalidArgumentException("Component ID must be an integer >= 0");
        }
    }

    public function setLowerClamp($lower_clamp)
    {
        if (is_float($lower_clamp) || is_int($lower_clamp)) {
            $this->lower_clamp = $lower_clamp;
        } else {
            throw new \InvalidArgumentException("Lower Clamp must be a number!");
        }
    }

    public function setDefault($default)
    {
        if (is_float($default) || is_int($default)) {
            $this->default = $default;
        } else {
            throw new \InvalidArgumentException("Default Value must be a number!");
        }
    }

    public function setMaxValue($max_value)
    {
        if (is_float($max_value) || is_int($max_value)) {
            $this->max_value = $max_value;
        } else {
            throw new \InvalidArgumentException("Max Value must be a number!");
        }
    }

    public function setUpperClamp($upper_clamp)
    {
        if (is_float($upper_clamp) || is_int($upper_clamp)) {
            $this->upper_clamp = $upper_clamp;
        } else {
            throw new \InvalidArgumentException("Lower Clamp must be a number!");
        }
    }


    /* Convenience functions for the different types of gradeables */

    public function isExtraCredit()
    {
        return $this->upper_clamp > $this->max_value;
    }

    // Numeric/Text
    public function getNumericScore()
    {
        return max($this->upper_clamp, $this->max_value);
    }

    // Electronic
    public function isCountUp()
    {
        return $this->default === 0;
    }

    public function isPenalty()
    {
        return $this->lower_clamp < 0;
    }

    public function getExtraCreditPoints()
    {
        return $this->upper_clamp - $this->max_value;
    }

    public function getPenaltyPoints()
    {
        return $this->isPenalty() ? abs($this->lower_clamp) : 0;
    }
}