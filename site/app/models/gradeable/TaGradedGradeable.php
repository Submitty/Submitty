<?php
/**
 * Created by PhpStorm.
 * User: mackek4
 * Date: 6/25/2018
 * Time: 2:30 PM
 */

namespace app\models\gradeable;


use app\libraries\Core;
use app\libraries\DateUtils;
use app\libraries\Utils;
use app\models\AbstractModel;
use app\models\User;

/**
 * Class TaGradedGradeable
 * @package app\models\gradeable
 *
 * @method string getOverallComment()
 * @method void setOverallComment($comment)
 * @method int getId()
 * @method \DateTime|null getUserViewedDate()
 * @method array[] getGradedComponents()
 */
class TaGradedGradeable extends AbstractModel {
    /** @property @var GradedGradeable A reference to the graded gradeable this Ta grade belongs to */
    private $graded_gradeable = null;
    /** @property @var int The id of this gradeable data */
    protected $id = 0;
    /** @property @var string The grader's overall comment */
    protected $overall_comment = "";
    /** @property @var \DateTime|null The date the user viewed their grade */
    protected $user_viewed_date = null;
    /** @property @var array[] The an array of arrays of GradedComponents, indexed by component id */
    protected $graded_components = [];
    /** @property @var GradedComponent[] The components that have been marked for deletion */
    private $deleted_graded_components = [];


    /**
     * TaGradedGradeable constructor.
     * @param Core $core
     * @param array $details A property-name-indexed array of values to construct with
     * @param GradedGradeable $graded_gradeable
     * @throws \InvalidArgumentException If any of the details are invalid or the graded gradeable is null
     */
    public function __construct(Core $core, GradedGradeable $graded_gradeable, array $details) {
        parent::__construct($core);

        if ($graded_gradeable === null) {
            throw new \InvalidArgumentException('Graded gradeable cannot be null');
        }
        $this->graded_gradeable = $graded_gradeable;

        $this->setIdFromDatabase($details['id'] ?? 0);
        $this->setOverallComment($details['overall_comment'] ?? '');
        $this->setUserViewedDate($details['user_viewed_date'] ?? null);
        $this->graded_gradeable->getGradeable()->setJustRegraded($details['just_regraded'] ?? false);
        $this->modified = false;
    }

    public function toArray() {
        $details = parent::toArray();

        // Make sure to convert the date into a string
        $details['user_viewed_date'] = $this->user_viewed_date !== null ? DateUtils::dateTimeToString($this->user_viewed_date) : null;

        // When serializing a graded gradeable, put the grader information into
        //  the graded gradeable instead of each component so if one grader  grades
        //  multiple components, their information only gets sent once
        $details['graders'] = [];
        /** @var GradedComponent[] $graded_components */
        foreach ($this->graded_components as $graded_components) {
            foreach ($graded_components as $graded_component) {
                if ($graded_component->getGrader() !== null) {
                    // Only set once if multiple components have the same grader
                    if (!isset($details['graders'][$graded_component->getGrader()->getId()])) {
                        $details['graders'][$graded_component->getGrader()->getId()] = $graded_component->getGrader()->toArray();
                    }
                }
            }
        }

        // Uncomment this block if we want to serialize these values all the time
//        $percent_graded = $this->getPercentGraded();
//        $percent_graded = is_nan($percent_graded) ? 0 : $percent_graded;
//        $details['percent_graded'] = $percent_graded;
//
//        $graded_percent = $this->getGradedPercent();
//        $graded_percent = is_nan($graded_percent) ? 0 : $graded_percent;
//        $details['graded_percent'] = $graded_percent;

        return $details;
    }

    /**
     * Gets all component grades for a given component
     * @param int $component_id The id of the component to get grades for
     * @return GradedComponent[] An array of component grades (empty if non exist)
     */
    public function getGradedComponentsByComponentId($component_id) {
        return $this->graded_components[$component_id] ?? [];
    }

    /**
     * Used to retrieve existing graded components or generate new ones
     * This function has fairly complex behavior to achieve a large amount of convenience.
     * In general: If the component is peer or generate is true, don't pass a null grader
     *
     * Grader Null:
     *   Component not peer:
     *     Component has grades:            => return the one TA grade
     *     Generate false:                  => return null
     *     Generate true:                   => throw InvalidArgumentException
     *   Component peer:                    => throw InvalidArgumentException
     * Grader Not Null:
     *   Component not peer:
     *     Component has grades:            => return the one TA grade and sets the grader
     *     Generate false:                  => return null
     *     Generate true:                   => return new component with provided user as grader (TA)
     *   Component peer:
     *     Component has grades for grader  => return that graded component
     *     Generate false:                  => return null
     *     Generate true:                   => return new component with provided user as grader (peer)
     *
     * @param Component $component The component the grade is for
     * @param User|null $grader The grader for this component
     * @param bool $generate If a new graded component should be generated if none were found
     * @return GradedComponent|null The graded component instance or null if not found
     * @throws \InvalidArgumentException If $grader is null and ($component is peer or $generate is true)
     */
    public function getOrCreateGradedComponent(Component $component, $grader = null, $generate = false) {
        $grades_exist = isset($this->graded_components[$component->getId()]);
        if ($grader === null) {
            // If the grader is null and its a peer component, we can't do anything useful
            if ($component->isPeer()) {
                throw new \InvalidArgumentException('Cannot get peer graded component with null grader');
            }

            // Grades exist, not a peer component, so grab the first grade
            if ($grades_exist) {
                return $this->graded_components[$component->getId()][0];
            }

            // If no grader is provided we can't generate a graded component
            if ($generate) {
                throw new \InvalidArgumentException('Cannot generate graded component with null grader');
            }

            // No grades exist, not trying to generate, not peer, no grader, so can't do anything
            return null;
        }

        //
        // Grader not null
        //

        if ($component->isPeer()) {
            // Try to find existing graded component for this component and user...
            /** @var GradedComponent[] $component_grades */
            $component_grades = $this->graded_components[$component->getId()] ?? [];
            $graded_component = null;
            foreach ($component_grades as $component_grade) {
                if ($component_grade->getGrader()->getId() === $grader->getId()) {
                    $graded_component = $component_grade;
                }
            }

            // ... Found one
            if ($graded_component !== null) {
                return $graded_component;
            }

            // None found, but generate one (append to array)
            if ($generate) {
                return $this->graded_components[$component->getId()][] =
                    new GradedComponent($this->core, $this, $component, $grader, []);
            }

            // None found. Don't generate one
            return null;
        }

        //
        // Not peer component
        //

        // Grades exist for component, so get the only one
        if ($grades_exist) {
            /** @var GradedComponent $graded_component */
            $graded_component = $this->graded_components[$component->getId()][0];
            $graded_component->setGrader($grader);
            return $graded_component;
        }

        // Grades don't exist, but generate one (at zero index of array)
        if ($generate) {
            return $this->graded_components[$component->getId()][0] =
                new GradedComponent($this->core, $this, $component, $grader, []);
        }

        // Grades don't exist.  Don't generate one
        return null;
    }

    /**
     * Gets the graded component with the provided id and grader
     * @param Component $component The component the grade is for
     * @param User|null $grader The grader for this component
     * @return GradedComponent|null
     * @throws \InvalidArgumentException If $grader is null and $component is peer
     */
    public function getGradedComponent(Component $component, $grader = null) {
        // The subset of the above function's features satisfy the
        //  expected behavior for a normal getter
        return $this->getOrCreateGradedComponent($component, $grader, false);
    }

    /**
     * Gets the graded gradeable instance this Ta grade belongs to
     * @return GradedGradeable
     */
    public function getGradedGradeable() {
        return $this->graded_gradeable;
    }

    /**
     * Gets the manual grading points the student earned
     * @return int
     */
    public function getGradedPoints() {
        $points_earned = 0.0;
        /** @var GradedComponent[] $graded_component */
        foreach ($this->graded_components as $graded_component) {
            if (count($graded_component) > 0) {
                $component_points_earned = 0.0;
                // Iterate through each grader for this component
                /** @var GradedComponent $component_grade */
                foreach ($graded_component as $component_grade) {
                    $component_points_earned += $component_grade->getTotalScore();
                }
                // TODO: how should peer grades be calculated: now its an average
                $points_earned += $component_points_earned / count($graded_component);
            }
        }
        return $points_earned;
    }

    /**
     * Gets the percent of points the student has earned of the
     *  components that have been graded
     * @param bool $clamp True to clamp the result to 1.0
     * @return float percentage (0 to 1), or NAN if no grading started
     */
    public function getGradedPercent($clamp = false) {
        return Utils::safeCalcPercent($this->getGradedPoints(),
            $this->getGradedGradeable()->getGradeable()->getTaNonExtraCreditPoints(), $clamp);
    }

    /**
     * Gets how much of this submitter's submission has been graded
     * @return float percentage (0 to 1), or NAN if no components
     */
    public function getPercentGraded() {
        $components_graded = 0.0;
        $components = $this->graded_gradeable->getGradeable()->getComponents();
        $gradeable = $this->graded_gradeable->getGradeable();

        $peer_component_count = array_sum(array_map(function (Component $component) {
            return $component->isPeer() ? 1 : 0;
        }, $components));
        $ta_component_count = count($components) - $peer_component_count;

        // For each peer component, there will be a certain number (set in gradeable) of peer graders
        //  For each non-peer component, there must be one grade (ta/instructor)
        $total_graders = $peer_component_count * $gradeable->getPeerGradeSet() + $ta_component_count;

        // Get the number of component grades
        foreach ($this->graded_components as $graded_component) {
            $components_graded += count($graded_component);
        }

        return Utils::safeCalcPercent($components_graded, $total_graders, true);
    }

    /**
     * Gets if this graded gradeable is completely graded
     * TODO this will need to change for peer grading
     * @return bool
     */
    public function isComplete() {
        return $this->getPercentGraded() >= 1.0;
    }

    /**
     * Sets the id of this grade data (used from database methods)
     * @param int $id
     * @internal
     */
    public function setIdFromDatabase($id) {
        if ((is_int($id) || ctype_digit($id)) && intval($id) >= 0) {
            $this->id = intval($id);
        } else {
            throw new \InvalidArgumentException('Id must be a non-negative integer');
        }
        // Reset the modified flag since this gets called once saved to db or constructor
        $this->modified = false;
    }

    /**
     * Sets the array of graded components for this gradeable data
     *  Note: only call from db methods for loading
     * @param array[]|GradedComponent[] $graded_components
     * @internal
     */
    public function setGradedComponentsFromDatabase(array $graded_components) {

        // Flatten the array if we are given a 2d array.  Don't trust the user to
        //  give us properly indexed components
        $graded_components_flat = [];
        foreach ($graded_components as $graded_component) {
            if (is_array($graded_component)) {
                $graded_components_flat = array_merge($graded_component, $graded_components_flat);
            } else {
                $graded_components_flat[] = $graded_component;
            }
        }

        // Next, setup the components to index by component id
        $graded_components_by_id = [];
        foreach ($graded_components_flat as $graded_component) {
            if ($graded_components)
                if (!($graded_component instanceof GradedComponent)) {
                    throw new \InvalidArgumentException('Graded Component array contained invalid type');
                }

            // Index by component id
            $graded_components_by_id[$graded_component->getComponentId()][] = $graded_component;
        }
        $this->graded_components = $graded_components_by_id;
    }

    /**
     * Clears the array of graded components en-route to deletion
     *  Note: only call from db methods for saving
     * @internal
     */
    public function clearDeletedGradedComponents() {
        $this->deleted_graded_components = [];
    }

    /**
     * Deletes the GradedComponent(s) associated with the provided Component and grader
     * @param Component $component The component to delete the grade for
     * @param User|null $grader The grader to delete the grade for, or null to delete all grades
     */
    public function deleteGradedComponent(Component $component, User $grader = null) {
        // If no grades exist, or this component isn't for this gradeable, don't do anything
        if (!isset($this->graded_components[$component->getId()])) {
            return;
        }

        if ($grader === null || !$component->getGradeable()->isPeerGrading()) {
            // If the grader is null or we aren't peer grading, then delete all component grades for this component
            $this->deleted_graded_components = array_merge($this->deleted_graded_components,
                $this->graded_components[$component->getId()]);

            // Remove the entry from the graded components array for this component
            unset($this->graded_components[$component->getId()]);
        } else {
            // Otherwise, only delete the component with the provided grader
            /** @var GradedComponent $graded_component */
            $new_component_array = [];
            foreach ($this->graded_components[$component->getId()] as $graded_component) {
                if ($graded_component->getGrader()->getId() === $grader->getId()) {
                    $this->deleted_graded_components[] = $graded_component;
                } else {
                    $new_component_array[] = $graded_component;
                }
            }

            // Set array to filtered array (without deleted components)
            if (count($new_component_array) === 0) {
                // If none are left, remove the entry from the graded components array for this component
                unset($this->graded_components[$component->getId()]);
            } else {
                $this->graded_components[$component->getId()] = $new_component_array;
            }
        }
    }

    /**
     * Gets the GradedComponents marked for deletion via deleteGradedComponent
     * @return GradedComponent[]
     */
    public function getDeletedGradedComponents() {
        return $this->deleted_graded_components;
    }

    /**
     * Sets the date that the user viewed their grade
     * @param string|\DateTime $user_viewed_date The date or date string of when the user viewed their grade
     * @throws \InvalidArgumentException if $grade_time is a string and failed to parse into a \DateTime object
     */
    public function setUserViewedDate($user_viewed_date) {
        if ($user_viewed_date === null) {
            $this->user_viewed_date = null;
        } else {
            try {
                $this->user_viewed_date = DateUtils::parseDateTime($user_viewed_date, $this->core->getConfig()->getTimezone());
            } catch (\Exception $e) {
                throw new \InvalidArgumentException('Invalid date string format');
            }
        }
        $this->modified = true;
    }

    /* Intentionally Unimplemented accessor methods */

    /** @internal */
    public function setId($id) {
        throw new \BadFunctionCallException('Cannot set id of gradeable data');
    }

    /** @internal */
    public function setGradedComponents(array $graded_components) {
        throw new \BadFunctionCallException('Cannot set graded components for grade.  Use getOrCreateGradedComponent instead');
    }
}
