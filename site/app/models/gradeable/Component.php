<?php

namespace app\models\gradeable;

use app\exceptions\ValidationException;
use app\exceptions\NotImplementedException;
use app\libraries\Core;
use app\libraries\Utils;
use app\models\AbstractModel;
use app\libraries\NumberUtils;

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
 * @method bool isPeerComponent()
 * @method void setPeerComponent($is_peer_component)
 * @method int getOrder()
 * @method void setOrder($order)
 * @method int getPage()
 * @method void setIsItempoolLinked($is_linked)
 * @method bool getIsItempoolLinked()
 * @method void setItempool($itempool_name)
 * @method string getItempool()
 * @method Mark[] getMarks()
 */
class Component extends AbstractModel {
    /** @var Gradeable Reference to the gradeable this belongs to */
    private $gradeable = null;
    /** @prop @var int The course-wide unique numeric id of this component */
    protected $id = 0;
    /** @prop @var string The title of this component */
    protected $title = "";
    /** @prop @var string The comment only visible to the TA/manual grader */
    protected $ta_comment = "";
    /** @prop @var string The comment visible to the student */
    protected $student_comment = "";
    /** @prop @var int The minimum points this component can contribute to the score (can be negative) */
    protected $lower_clamp = 0;
    /** @prop @var int The number of points this component is worth with no marks */
    protected $default = 0;
    /** @prop @var int The full value of this component without extra credit */
    protected $max_value = 0;
    /** @prop @var int The maximum number of points this component can contribute to the score (can be > $max_value) */
    protected $upper_clamp = 0;
    /** @prop @var bool If this is a text component (true) or a numeric component (false) for numeric/text components */
    protected $text = false;
    /** @prop @var bool If this is a peer grading component */
    protected $peer_component = false;
    /** @prop @var int The order of this component in the gradeable */
    protected $order = -1;
    /** @prop @var int The pdf page this component will reside in */
    protected $page = -1;

    /** @prop @var bool Whether this component is linked to some itempool or not */
    protected $is_itempool_linked = false;
    /** @prop @var string Name of the itempool item if it is linked to itempool else empty string */
    protected $itempool = "";

    /** @prop @var Mark[] All possible common marks that can be assigned to this component */
    protected $marks = [];

    /** @prop @var Mark[] Array of marks loaded from the database */
    private $db_marks = [];
    /** @prop @var bool If any submitters have grades for this component */
    private $any_grades = false;

    /** @var int Pass to setPage to indicate student-assigned pdf page */
    const PDF_PAGE_STUDENT = -1;
    /** @var int Pass to setPage to indicate no pdf page */
    const PDF_PAGE_NONE = 0;

    /**
     * Component constructor.
     * @param Core $core
     * @param Gradeable $gradeable
     * @param $details
     * @throws \InvalidArgumentException if any of the details were not found or invalid, or the gradeable was null
     * @throws ValidationException If the provided point details are incompatible
     */
    public function __construct(Core $core, Gradeable $gradeable, $details) {
        parent::__construct($core);

        $this->setGradeable($gradeable);
        $this->setIdInternal($details['id']);
        $this->setTitle($details['title']);
        $this->setTaComment($details['ta_comment']);
        $this->setStudentComment($details['student_comment']);
        $this->setPoints($details);
        $this->setText($details['text']);
        $this->setPeerComponent($details['peer']);
        $this->setOrder($details['order']);
        $this->setPage($details['page']);
        $this->setIsItempoolLinked($details['is_itempool_linked'] ?? false);
        $this->setItempool($details['itempool'] ?? "");
        $this->any_grades = ($details['any_grades'] ?? false) === true;
        $this->modified = false;
    }

    /**
     * Creates a component with marks from a nested array (from JSON typically)
     * @param Core $core
     * @param Gradeable $gradeable
     * @param array $arr
     * @return Component
     */
    public static function import(Core $core, Gradeable $gradeable, array $arr) {
        $component = new Component($core, $gradeable, $arr);

        $marks_arr = $arr['marks'] ?? [];
        foreach ($marks_arr as $mark_arr) {
            $component->importMark($mark_arr);
        }
        return $component;
    }

    /**
     * Exports the component and its marks to an array
     * @return array
     */
    public function export() {
        $arr = parent::toArray();
        unset($arr['any_grades']);
        unset($arr['id']);
        unset($arr['modified']);
        unset($arr['order']);
        $marks = [];
        foreach ($arr['marks'] as $mark) {
            unset($mark['any_receivers']);
            unset($mark['id']);
            unset($mark['order']);
            unset($mark['modified']);
            $marks[] = $mark;
        }
        $arr['marks'] = $marks;
        return $arr;
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
     * @return Mark|null The Mark with the provided id
     * @throws \InvalidArgumentException If the provided mark id isn't part of this component
     */
    public function getMark($mark_id) {
        foreach ($this->marks as $mark) {
            if ($mark->getId() === $mark_id) {
                return $mark;
            }
        }
        throw new \InvalidArgumentException('Component did not contain provided mark id');
    }

    /**
     * Gets an array of marks set to be deleted
     * @return Mark[]
     */
    public function getDeletedMarks() {
        return array_udiff($this->db_marks, $this->marks, Utils::getCompareByReference());
    }

    /**
     * Gets the number of grades required for this component
     *  to be considered 100% graded
     * @return int
     */
    public function getGradingSet() {
        return $this->peer_component ? $this->gradeable->getPeerGradeSet() : 1;
    }

    /**
     * Gets if any submitters have grades for this component yet
     * @return bool
     */
    public function anyGrades() {
        return $this->any_grades;
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

    /**
     * Sets the page number for this component
     * @param int $page
     */
    public function setPage(int $page) {
        $this->page = max($page, -1);
        $this->modified = true;
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
            }
            else {
                $parsedPoints[$property] = null;
            }
        }
        return $parsedPoints;
    }

    /**
     * Asserts that the point values are valid.  See `setPoints` docs for details
     *
     * @param array $points A complete array of floats (or nulls) indexed by component point property
     */
    private function assertPoints(array $points) {
        $errors = [];

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
            $this->$property = NumberUtils::roundPointValue($points[$property], $this->getGradeable()->getPrecision());
        }
        $this->modified = true;
    }

    /**
     * Sets the array of marks
     * @param Mark[] $marks Must be an array of only Marks
     */
    public function setMarks(array $marks) {
        $marks = array_values($marks);
        // Make sure we're getting only marks
        foreach ($marks as $mark) {
            if (!($mark instanceof Mark)) {
                throw new \InvalidArgumentException('Object in marks array wasn\'t a mark');
            }
        }

        // Get the implied deleted marks from this operation and make sure that we aren't
        //  deleting any marks that are in use.
        $deleted_marks = array_udiff($this->marks, $marks, Utils::getCompareByReference());
        if (
            in_array(
                true,
                array_map(
                    function (Mark $mark) {
                        return $mark->anyReceivers();
                    },
                    $deleted_marks
                )
            )
        ) {
            throw new \InvalidArgumentException('Call to setMarks implied deletion of marks with receivers');
        }

        $this->marks = $marks;

        // sort by order
        usort($this->marks, function (Mark $a, Mark $b) {
            return $a->getOrder() - $b->getOrder();
        });
    }

    /**
     * Adds a new mark to this component with the provided title and point value
     * @param string $title
     * @param float $points
     * @return Mark the created mark
     */
    public function addMark(string $title, float $points, bool $publish) {
        $mark = new Mark($this->core, $this, [
            'title' => $title,
            'points' => $points,
            'order' => count($this->marks),
            'publish' => $publish,
            'id' => 0
        ]);
        $this->marks[] = $mark;
        return $mark;
    }

    /**
     * Imports a mark into the component via array
     * @param $details
     * @return Mark
     */
    public function importMark($details) {
        $details['id'] = 0;
        $details['order'] = count($this->getMarks());
        $mark = new Mark($this->core, $this, $details);
        $this->marks[] = $mark;
        return $mark;
    }


    /**
     * Base method for deleting marks.  This isn't exposed as public so
     *  its make very clear that a delete mark operation is being forceful.
     * @param Mark $mark
     * @param bool $force true to delete the mark if it has receivers
     * @throws \InvalidArgumentException If this component doesn't own the provided mark or
     *          $force is false and the mark has receivers
     */
    private function deleteMarkInner(Mark $mark, bool $force = false) {
        // Don't delete if the mark has receivers (and we aren't forcing)
        if ($mark->anyReceivers() && !$force) {
            throw new \InvalidArgumentException('Attempt to delete a mark with receivers!');
        }

        // Calculate our marks array without the provided mark
        $new_marks = array_udiff($this->marks, [$mark], Utils::getCompareByReference());

        // If it wasn't removed from our marks, it was either already deleted, or never belonged to us
        if (count($new_marks) === count($this->marks)) {
            throw new \InvalidArgumentException('Attempt to delete mark that did not belong to this component');
        }

        // Finally, set our array to the new one
        $this->marks = $new_marks;
    }

    /**
     * Deletes a mark from this component if it has no receivers
     * @param Mark $mark
     * @throws \InvalidArgumentException If this component doesn't own the provided mark or the mark has receivers
     */
    public function deleteMark(Mark $mark) {
        $this->deleteMarkInner($mark, false);
    }

    /**
     * Deletes a mark from this component without checking if a submitter has received it yet
     * @param Mark $mark
     * @throws \InvalidArgumentException If this component doesn't own the provided mark
     */
    public function forceDeleteMark(Mark $mark) {
        $this->deleteMarkInner($mark, true);
    }

    /**
     * Sets the array of marks, only to be called from the database loading methods
     * @param array $marks
     * @internal
     */
    public function setMarksFromDatabase(array $marks) {
        $this->setMarks($marks);
        $this->db_marks = $this->marks;
    }

    /**
     * Sets the component Id
     * @param int $id Must be a non-negative integer
     */
    private function setIdInternal($id) {
        if ((is_int($id) || ctype_digit($id)) && intval($id) >= 0) {
            $this->id = intval($id);
        }
        else {
            throw new \InvalidArgumentException('Component Id must be a non-negative integer');
        }
    }

    /**
     * Sets the id of the component.
     *  NOTE: this should only be called from database results
     *  to avoid reconstruction of the whole object
     * @param int $id
     * @internal
     */
    public function setIdFromDatabase($id) {
        $this->setIdInternal($id);
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

    /**
     * Gets if this component is fully extra credit
     * @return bool
     */
    public function isExtraCredit() {
        return $this->max_value === $this->default && $this->hasExtraCredit();
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
        return $this->hasPenalty() ? abs($this->lower_clamp) : 0;
    }
}
