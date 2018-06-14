<?php

namespace app\models\gradeable;

use app\exceptions\AggregateException;
use app\exceptions\NotImplementedException;
use app\libraries\Core;
use app\models\AbstractModel;


/**
 * Class Component
 * @package app\models\gradeable
 *
 * All data describing the configuration of a Gradeable Component
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
class Component extends AbstractModel
{
    /** @var Gradeable Reference to the gradeable this belongs to */
    private $gradeable = null;
    /** @property @var int The course-wide unique numeric id of this component */
    protected $id = 0;
    /** @property @var string The title of this component */
    protected $title = "";
    /** @property @var string The comment only visible to the TA/manual grader */
    protected $ta_comment = "";
    /** @property @var string The comment visible to the student */
    protected $student_comment = "";
    /** @property @var int The minimum points this component can contribute to the score (can be negative) */
    protected $lower_clamp = 0;
    /** @property @var int The number of points this component is worth with no marks */
    protected $default = 0;
    /** @property @var int The full value of this component without extra credit */
    protected $max_value = 0;
    /** @property @var int The maximum number of points this component can contribute to the score (can be > $max_value) */
    protected $upper_clamp = 0;
    /** @property @var bool If this is a text component (true) or a numeric component (false) for numeric/text components */
    protected $text = false;
    /** @property @var bool If this is a peer grading component */
    protected $peer = false;
    /** @property @var int The order of this component in the gradeable */
    protected $order = -1;
    /** @property @var int The pdf page this component will reside in */
    protected $page = -1;

    /** @property @var Mark[] All possible common marks that can be assigned to this component */
    protected $marks = array();


    public function __construct(Core $core, Gradeable $gradeable, $details, array $marks)
    {
        parent::__construct($core);

        $this->setGradeable($gradeable);
        $this->setMarks($marks);
        $this->setId($details['id']);
        $this->setTitle($details['title']);
        $this->setTaComment($details['ta_comment']);
        $this->setStudentComment($details['student_comment']);
        $this->setPoints($details['lower_clamp'], $details['default'], $details['max_value'], $details['upper_clamp']);
        $this->setText($details['text']);
        $this->setPeer($details['peer']);
        $this->setOrder($details['order']);
        $this->setPage($details['page']);
    }

    public function getGradeable()
    {
        return $this->gradeable;
    }

    /* Overridden setters with validation */
    private function setGradeable(Gradeable $gradeable)
    {
        if($gradeable === null) {
            throw new \InvalidArgumentException("Gradeable Cannot be null!");
        }
        $this->gradeable = $gradeable;
    }

    private function validatePoints(&$lower_clamp, &$default, &$max_value, &$upper_clamp)
    {
        $errors = array();
        if (!is_numeric($lower_clamp)) {
            $lower_clamp = null;
            $errors['lower_clamp'] = "Value must be a number!";
        } else {
            $lower_clamp = floatval($lower_clamp);
        }
        if (!is_numeric($default)) {
            $default = null;
            $errors['default'] = "Value must be a number!";
        } else {
            $default = floatval($default);
        }
        if (!is_numeric($max_value)) {
            $max_value = null;
            $errors['max_value'] = "Value must be a number!";
        } else {
            $max_value = floatval($max_value);
        }
        if (!is_numeric($upper_clamp)) {
            $upper_clamp = null;
            $errors['upper_clamp'] = "Value must be a number!";
        } else {
            $upper_clamp = floatval($upper_clamp);
        }

        if (!($lower_clamp === null || $default === null) && $lower_clamp > $default) {
            $errors['lower_clamp'] = "Lower clamp can't be more than default!";
        }
        if (!($default === null || $max_value === null) && $default > $max_value) {
            $errors['max_value'] = "Max value can't be less than default!";
        }
        if (!($max_value === null || $upper_clamp === null) && $max_value > $upper_clamp) {
            $errors['max_value'] = "Max value can't be more than upper clamp!";
        }

        if (count($errors) === 0)
            return null;
        return $errors;
    }
    public function setPoints($lower_clamp, $default, $max_value, $upper_clamp)
    {
        $messages = $this->validatePoints($lower_clamp, $default, $max_value, $upper_clamp);
        if ($messages !== null) {
            throw new AggregateException("Component Points Error!", $messages);
        }

        $this->lower_clamp = $lower_clamp;
        $this->default = $default;
        $this->max_value = $max_value;
        $this->upper_clamp = $upper_clamp;
    }

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

    private function setId($id)
    {
        if (is_int($id) && $id >= 0) {
            $this->id = $id;
        } else {
            throw new \InvalidArgumentException("Component ID must be an integer >= 0");
        }
    }

    private function setLowerClamp($lower_clamp)
    {
        throw new NotImplementedException('Individual setters are disabled, use "setPoints" instead');
    }

    private function setDefault($default)
    {
        throw new NotImplementedException('Individual setters are disabled, use "setPoints" instead');
    }

    private function setMaxValue($max_value)
    {
        throw new NotImplementedException('Individual setters are disabled, use "setPoints" instead');
    }

    private function setUpperClamp($upper_clamp)
    {
        throw new NotImplementedException('Individual setters are disabled, use "setPoints" instead');
    }


    /* Convenience functions for the different types of gradeables */

    public function hasExtraCredit()
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

    public function hasPenalty()
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