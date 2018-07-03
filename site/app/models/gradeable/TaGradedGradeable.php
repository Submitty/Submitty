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

/**
 * Class TaGradedGradeable
 * @package app\models\gradeable
 *
 * @method string getOverallComment()
 * @method void setOverallComment($comment)
 * @method int getId()
 * @method \DateTime|null getUserViewedDate()
 * @method array getGradedComponents()
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
    /** @property @var GradedComponent[][] The an array of arrays of GradedComponents, indexed by component id */
    protected $graded_components = array();


    /**
     * TaGradedGradeable constructor.
     * @param Core $core
     * @param array $details A property-name-indexed array of values to construct with
     * @param GradedGradeable $graded_gradeable
     * @throws \Exception If the 'user_viewed_date' in the $details array is an invalid DateTime/date-string
     */
    public function __construct(Core $core, GradedGradeable $graded_gradeable, array $details) {
        parent::__construct($core);

        if($graded_gradeable === null) {
            throw new \InvalidArgumentException('Graded gradeable cannot be null');
        }
        $this->graded_gradeable = $graded_gradeable;

        $this->setIdInternal($details['id']);
        $this->setOverallComment($details['overall_comment']);
        $this->setUserViewedDate($details['user_viewed_date']);
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
        foreach ($this->graded_components as $graded_components) {
            foreach($graded_components as $graded_component) {
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
     * Gets the graded gradeable instance this Ta grade belongs to
     * @return GradedGradeable
     */
    public function getGradedGradeable() {
        return $this->graded_gradeable;
    }

    /**
     * Gets the percent of points the student has earned of the
     *  components that have been graded
     * @param bool $clamp True to clamp the result to 1.0
     * @return float percentage (0 to 1), or NAN if no grading started
     */
    public function getGradedPercent($clamp = false) {
        $points_earned = 0.0;
        $points_possible = 0.0;

        // Iterate through each component
        /** @var GradedComponent[] $graded_component */
        foreach ($this->graded_components as $graded_component) {
            if (count($graded_component) > 0) {
                $component_points_earned = 0.0;
                // Iterate through each grader for this component
                /** @var GradedComponent $component_grade */
                foreach ($graded_component as $component_grade) {
                    // Be sure to add the default so count-down gradeables don't become negative
                    $component_points_earned += $component_grade->getComponent()->getDefault();

                    // TODO: how should peer grades be calculated: now its an average
                    $component_points_earned += $component_grade->getScore();
                    foreach($component_grade->getMarks() as $mark) {
                        $component_points_earned += $mark->getPoints();
                    }
                }
                $points_earned += $component_points_earned / count($graded_component);
                $points_possible += $graded_component[0]->getComponent()->getMaxValue();
            }
        }

        return Utils::safeCalcPercent($points_earned, $points_possible, $clamp);
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
     * Sets the id of this grade data
     * @param int $id
     */
    private function setIdInternal($id) {
        if ((is_int($id) || ctype_digit($id)) && intval($id) >= 0) {
            $this->id = intval($id);
        } else {
            throw new \InvalidArgumentException('Id must be a non-negative integer');
        }
    }

    /**
     * Sets the array of graded components for this gradeable data
     * @param GradedComponent[][]|GradedComponent[] $graded_components
     */
    public function setGradedComponents(array $graded_components) {

        // Flatten the array if we are given a 2d array.  Don't trust the user to
        //  give us properly indexed components
        $graded_components_flat = [];
        foreach($graded_components as $graded_component) {
            if(is_array($graded_component)) {
                $graded_components_flat = array_merge($graded_component, $graded_components_flat);
            } else {
                $graded_components_flat[] = $graded_component;
            }
        }

        // Next, setup the components to index by component id
        $graded_components_by_id = [];
        foreach ($graded_components_flat as $graded_component) {
            if($graded_components)
                if (!($graded_component instanceof GradedComponent)) {
                    throw new \InvalidArgumentException('Graded Component array contained invalid type');
                }

            // Index by component id
            if(isset($graded_components_by_id[$graded_component->getComponentId()])) {
                $graded_components_by_id[$graded_component->getComponentId()][] = $graded_component;
            } else {
                $graded_components_by_id[$graded_component->getComponentId()] = [$graded_component];
            }
        }
        $this->graded_components = $graded_components_by_id;
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

}