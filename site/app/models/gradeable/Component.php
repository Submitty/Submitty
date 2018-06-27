<?php

namespace app\models\gradeable;

use app\exceptions\ValidationException;
use app\exceptions\NotImplementedException;
use app\libraries\Core;
use app\libraries\Utils;
use app\models\AbstractModel;


/**
 * Class Component
 * @package app\models\gradeable
 *
 * All data describing the configuration of a Gradeable Component
 *
 * @method int getId()
 * @method string getTitle()
 * @method void setTitle($title)
 * @method string getTaComment()
 * @method void setTaComment($ta_comment)
 * @method string getStudentComment()
 * @method void setStudentComment($student_comment)
 * @method float getLowerClamp()
 * @method float getDefault()
 * @method float getMaxValue()
 * @method float getUpperClamp()
 * @method bool isText()
 * @method void setText($is_text)
 * @method bool isPeer()
 * @method void setPeer($is_peer)
 * @method int getOrder()
 * @method void setOrder($order)
 * @method int getPage()
 * @method void setPage($page)
 * @method Mark[] getMarks()
 */
class Component extends AbstractModel {
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


    public function __construct(Core $core, Gradeable $gradeable, $details, array $marks) {
        parent::__construct($core);

        $this->setGradeable($gradeable);
        $this->setMarks($marks);
        $this->setIdInternal($details['id']);
        $this->setTitle($details['title']);
        $this->setTaComment($details['ta_comment']);
        $this->setStudentComment($details['student_comment']);
        $this->setPoints($details);
        $this->setText($details['text']);
        $this->setPeer($details['peer']);
        $this->setOrder($details['order']);
        $this->setPage($details['page']);
    }

    /**
     * Gets the component's gradeable
     * @return Gradeable The component's gradeable
     */
    public function getGradeable() {
        return $this->gradeable;
    }

    /**
     * Gets the mark object with the provided mark id
     * @param int $mark_id
     * @return Mark|null The Mark with the provided id, or null if not found
     */
    public function getMark($mark_id) {
        foreach($this->marks as $mark) {
            if($mark->getId() === $mark_id) {
                return $mark;
            }
        }
        return null;
    }

    /* Overridden setters with validation */
    /**
     * Sets the component's gradeable
     * @param Gradeable $gradeable A non-null gradeable
     */
    private function setGradeable(Gradeable $gradeable) {
        if ($gradeable === null) {
            throw new \InvalidArgumentException('Gradeable Cannot be null!');
        }
        $this->gradeable = $gradeable;
    }

    const point_properties = [
        'lower_clamp',
        'default',
        'max_value',
        'upper_clamp'
    ];

    /**
     * Parses points from string or float values into all float / null values
     * @param array $points A partial or complete array of floats or numeric strings indexed by component point property
     * @return array A complete array of floats (or nulls) indexed by component point property
     */
    private function parsePoints(array $points) {
        $parsedPoints = [];
        foreach (self::point_properties as $property) {
            if (is_numeric($points[$property])) {
                $parsedPoints[$property] = floatval($points[$property]);
            } else {
                $parsedPoints[$property] = null;
            }
        }
        return $parsedPoints;
    }

    /**
     * Asserts that the point values are valid.  See `setPoints` docs for details
     *
     * @param array $points An complete array of floats (or nulls) indexed by component point property
     */
    private function assertPoints(array $points) {
        $errors = array();

        // Give error messages to all null elements
        foreach (self::point_properties as $property) {
            if ($points[$property] === null) {
                $errors[$property] = 'Value must be a number!';
            }
        }

        //
        // NOTE: The function `Utils::compareNullableGt(a,b)` in this context is called so that
        //          it returns TRUE if the two values being compared are incompatible.  If the function
        //          returns FALSE then either the condition a>b is false, or one of the values are null.
        //          THIS NULL CASE MUST BE HANDLED IN SOME OTHER WAY.  As you can see, this is achieved by
        //          null checks in the above foreach loop.
        //
        //    i.e. if 'lower_clamp' > 'default', then set an error, but if 'lower_clamp' <= 'default' there is no error
        //      In the case that either 'lower_clamp' or 'default' are null, the function will return
        //      FALSE.  In the above foreach loop, if any of the properties are null, then an error gets
        //      assigned to that property name.  Any of the below comparisons involving that property are irrelevant
        //      because comparisons involving null values are irrelevant.
        //

        if (Utils::compareNullableGt($points['lower_clamp'], $points['default'])) {
            $errors['lower_clamp'] = 'Lower clamp can\'t be more than default!';
        }
        if (Utils::compareNullableGt($points['default'], $points['max_value'])) {
            $errors['max_value'] = 'Max value can\'t be less than default!';
        }
        if (Utils::compareNullableGt($points['max_value'], $points['upper_clamp'])) {
            $errors['max_value'] = 'Max value can\'t be more than upper clamp!';
        }

        if (count($errors) !== 0) {
            throw new ValidationException('Component point validation failed', $errors);
        }
    }

    /**
     * Sets component point values and ensures they are consistent:
     *  lower_clamp <= default <= max_value <= upper_clamp
     *
     * This will round the parameters to the precision of the gradeable
     *
     * @param array $points A partial or complete array of floats or numeric strings indexed by component point property
     */
    public function setPoints(array $points) {
        // Wrangle the input to ensure that they're all either floats are null
        $points = $this->parsePoints($points);

        // Assert that the point values are valid
        $this->assertPoints($points);

        // Round after validation because of potential floating point weirdness
        foreach (self::point_properties as $property) {
            $this->$property = $this->getGradeable()->roundPointValue($points[$property]);
        }
    }

    /**
     * Sets the array of marks
     * @param Mark[] $marks Must be an array of only Marks
     */
    public function setMarks(array $marks) {
        // Make sure we're getting only marks
        foreach ($marks as $mark) {
            if (!($mark instanceof Mark)) {
                throw new \InvalidArgumentException('Object in marks array wasn\'t a mark');
            }
        }
        $this->marks = $marks;
    }

    /**
     * Sets the component Id
     * @param int $id Must be a non-negative integer
     */
    private function setIdInternal($id) {
        if (is_int($id) && $id >= 0) {
            $this->id = $id;
        } else {
            throw new \InvalidArgumentException('Component Id must be an integer >= 0');
        }
    }

    /** @internal */
    public function setId($id) {
        throw new \BadFunctionCallException('Cannot set Id of component');
    }

    /** @internal */
    public function setLowerClamp($lower_clamp) {
        throw new NotImplementedException('Individual setters are disabled, use "setPoints" instead');
    }

    /** @internal */
    public function setDefault($default) {
        throw new NotImplementedException('Individual setters are disabled, use "setPoints" instead');
    }

    /** @internal */
    public function setMaxValue($max_value) {
        throw new NotImplementedException('Individual setters are disabled, use "setPoints" instead');
    }

    /** @internal */
    public function setUpperClamp($upper_clamp) {
        throw new NotImplementedException('Individual setters are disabled, use "setPoints" instead');
    }


    /* Convenience functions for the different types of gradeables */

    public function hasExtraCredit() {
        return $this->upper_clamp > $this->max_value;
    }

    // Numeric/Text
    public function getNumericScore() {
        return max($this->upper_clamp, $this->max_value);
    }

    // Electronic
    public function isCountUp() {
        return $this->default === 0;
    }

    public function hasPenalty() {
        return $this->lower_clamp < 0;
    }

    public function getExtraCreditPoints() {
        return $this->upper_clamp - $this->max_value;
    }

    public function getPenaltyPoints() {
        return $this->isPenalty() ? abs($this->lower_clamp) : 0;
    }
}
