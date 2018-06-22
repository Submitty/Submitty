<?php

namespace app\models\gradeable;

use app\models\AbstractModel;
use app\libraries\Core;
use app\libraries\DateUtils;
use app\models\User;

/**
 * Class GradedComponent
 * @package app\models\gradeable
 *
 * @method int getComponentId()
 * @method float getScore()
 * @method string getComment()
 * @method void setComment($comment)
 * @method string getGraderId()
 * @method int getGradedVersion()
 * @method void setGradedVersion($graded_version)
 * @method \DateTime getGradeTime()
 */
class GradedComponent extends AbstractModel {
    /** @var Component Reference to component */
    private $component = null;
    /** @var GradedGradeable Reference to the gradeable data */
    private $graded_gradeable = null;
    /** @property @var string Id of the component this grade is attached to */
    protected $component_id = 0;

    /** @var User The grader of this component */
    private $grader = null;

    /** @var Mark[] References to the marks this graded component received */
    private $marks = array();

    /** @property @var int[] The mark ids the submitter received for this component */
    protected $mark_ids = array();

    /** @property @var float The score for this component */
    protected $score = 0;
    /** @property @var string The comment on this mark / custom mark description */
    protected $comment = "";
    /** @property @var string The Id of the grader who most recently updated the component's grade */
    protected $grader_id = "";
    /** @property @var int The submission version this grade is for */
    protected $graded_version = 0;
    /** @property @var \DateTime The time which this grade was most recently updated */
    protected $grade_time = null;

    /**
     * GradedComponent constructor.
     * @param Core $core
     * @param GradedGradeable $graded_gradeable The full graded gradeable associated with this component
     * @param User $grader The user who graded this component
     * @param int $component_id The component id associated with this grade
     * @param int[] $mark_ids The mark ids this graded component received
     * @param array $details any remaining properties
     * @throws \Exception if the 'grade_time' value in the $details array is not a valid DateTime/date-string
     */
    public function __construct(Core $core, GradedGradeable $graded_gradeable, User $grader, $component_id, array $mark_ids, array $details) {
        parent::__construct($core);

        $this->setGradedGradeable($graded_gradeable);
        $this->setComponent($graded_gradeable->getGradeable()->getComponent($component_id));
        $this->setGrader($grader);

        // This may seem redundant, but by fetching the marks from the component and calling setMarks, we
        //  effectively filter out any of the invalid values in $mark_ids
        $mark_objects = [];
        foreach($this->component->getMarks() as $mark) {
            if(in_array($mark->getId(), $mark_ids)) {
                $mark_objects[] = $mark;
            }
        }
        $this->setMarks($mark_objects);

        $this->setComment($details['comment']);
        $this->setGradedVersion($details['graded_version']);
        $this->setGradeTime($details['grade_time']);

        // Make sure the loaded score overrides the calculated
        //  score if it exists / there aren't any marks
        if (isset($details['score'])) {
            $this->setScore($details['score']);
        }
        $this->modified = false;
    }

    public function toArray() {
        $details = parent::toArray();

        // Make sure to convert the date into a string
        $details['grade_time'] = DateUtils::dateTimeToString($this->grade_time);

        return $details;
    }

    /**
     * Gets the component associated with this component data
     * @return Component
     */
    public function getComponent() {
        return $this->component;
    }

    /**
     * Gets the GradedGradeable this component belongs to
     * @return GradedGradeable
     */
    public function getGradedGradeable() {
        return $this->graded_gradeable;
    }

    /**
     * Gets the user who graded this component
     * @return User
     */
    public function getGrader() {
        return $this->grader;
    }

    /**
     * Gets references to the marks that the submitter received
     * @return Mark[]
     */
    public function getMarks() {
        return $this->marks;
    }

    /* Overridden setters with validation */

    /**
     * Sets the component reference for this data
     * @param Component $component
     */
    private function setComponent(Component $component) {
        if ($component === null) {
            throw new \InvalidArgumentException('Component cannot be null');
        }
        $this->component = $component;
        $this->component_id = $component->getId();
    }

    private function setGradedGradeable(GradedGradeable $graded_gradeable) {
        if ($graded_gradeable === null) {
            throw new \InvalidArgumentException('Graded gradeable cannot be null');
        }
        $this->graded_gradeable = $graded_gradeable;
    }

    /**
     * Calculates the score the submitter received for this component
     *  based on the $marks array
     */
    private function calculateScore() {
        $total_points = 0.0;

        foreach ($this->marks as $mark) {
            $total_points += $mark->getPoints();
        }
        $this->setScore($total_points);
    }

    /**
     * Sets the marks the submitter received for this component
     * @param array $marks
     */
    public function setMarks(array $marks) {
        $new_mark_ids = [];
        foreach ($marks as $mark) {
            if (!($mark instanceof Mark)) {
                throw new \InvalidArgumentException('Object in marks array was not a mark');
            }
            $new_mark_ids[] = $mark->getId();
        }
        $this->marks = $marks;
        $this->mark_ids = $new_mark_ids;

        $this->calculateScore();
    }

    /**
     * Sets the last time this component data was changed
     * @param \DateTime|string $grade_time Either a \DateTime object, or a date-time string
     * @throws \Exception if $grade_time is a string and failed to parse into a \DateTime object
     */
    public function setGradeTime($grade_time) {
        if ($grade_time === null) {
            $this->grade_time = null;
        } else {
            $this->grade_time = DateUtils::parseDateTime($grade_time, $this->core->getConfig()->getTimezone());
        }
    }

    /**
     * Sets the user who most recently changed the component data
     * @param User $grader
     */
    public function setGrader(User $grader) {
        $this->grader = $grader;
        $this->grader_id = $this->grader !== null ? $grader->getId() : '';
    }

    /**
     * Sets the score the submitter received for this component, clamped to be
     *  between the lower and upper clamp of the associated component
     * @param float $score
     */
    public function setScore($score) {
        // clamp the score (no error if not in bounds)
        //  min(max(a,b),c) will clamp the value 'b' in the range [a,c]
        $this->score = min(max($this->component->getLowerClamp(), $score), $this->component->getUpperClamp());
    }

    /* Intentionally Unimplemented accessor methods */

    /** @internal */
    public function setComponentId() {
        throw new \BadFunctionCallException('Cannot set component data\'s component Id');
    }

    /** @internal */
    public function setGraderId() {
        throw new \BadFunctionCallException('Cannot set grader Id');
    }

    /** @internal */
    public function setMarkIds() {
        throw new \BadFunctionCallException('Cannot set mark ids');
    }
}
