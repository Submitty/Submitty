<?php

namespace app\models\gradeable;

use app\libraries\Core;
use app\libraries\DateUtils;
use \app\models\AbstractModel;

/**
 * Class GradedGradeable
 * @package app\models\gradeable
 *
 * @method string getGradeableId()
 * @method GradedComponent[][] getGradedComponents()
 * @method AutogradingVersion[] getAutogradingVersions()
 * @method Submitter getSubmitter()
 * @method int getId()
 * @method string getOverallComment()
 * @method void setOverallComment($comment)
 * @method \DateTime getUserViewedDate()
 * @method int getActiveVersion()
 */
class GradedGradeable extends AbstractModel {
    /** @var Gradeable Reference to gradeable */
    private $gradeable = null;
    /** @property @var string Id of the gradeable this grade is attached to */
    protected $gradeable_id = "";

    /** @property @var int The id of this gradeable data */
    protected $id = 0;
    /** @property @var string The grader's overall comment */
    protected $overall_comment = "";
    /** @property @var \DateTime The date the user viewed their grade */
    protected $user_viewed_date = null;
    /** @property @var int The active submission version for electronic gradeables */
    protected $active_version = 0;

    /** @property @var Submitter The submitter who received this graded gradeable */
    protected $submitter = null;
    /** @property @var GradedComponent[][] The an array of arrays of GradedComponents, indexed by component id */
    protected $graded_components = array();
    /** @property @var array AutogradingVersion[] The graded versions for electronic gradeables */
    protected $graded_versions = array();


    /**
     * GradedGradeable constructor.
     * @param Core $core
     * @param Gradeable $gradeable The gradeable associated with this grade
     * @param Submitter $submitter The user or team who submitted for this graded gradeable
     * @param array $details A property-name-indexed array of values to construct with
     * @throws \Exception If the 'user_viewed_date' in the $details array is an invalid DateTime/date-string
     */
    public function __construct(Core $core, Gradeable $gradeable, Submitter $submitter, array $details) {
        parent::__construct($core);

        $this->setGradeable($gradeable);
        $this->setSubmitterInternal($submitter);

        $this->setIdInternal($details['id']);
        $this->setOverallComment($details['overall_comment']);
        $this->setUserViewedDate($details['user_viewed_date']);
        $this->setActiveVersion($details['active_version']);
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

        return $details;
    }

    /**
     * Gets the gradeable this grade data is associated with
     * @return Gradeable the gradeable this grade data is associated with
     */
    public function getGradeable() {
        return $this->gradeable;
    }

    /* Overridden setters with validation */

    /**
     * Sets the internal gradeable reference
     * @param Gradeable $gradeable
     */
    private function setGradeable(Gradeable $gradeable) {
        if ($gradeable === null) {
            throw new \InvalidArgumentException('Gradeable cannot be null');
        }
        $this->gradeable = $gradeable;
        $this->gradeable_id = $gradeable->getId();
    }

    /**
     * Sets the submitter for this grade data
     * @param Submitter $submitter
     */
    private function setSubmitterInternal(Submitter $submitter) {
        if ($submitter === null) {
            throw new \InvalidArgumentException('Submitter cannot be null');
        }
        $this->submitter = $submitter;
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
     * Sets the array of graded versions for this gradeable data
     * @param AutogradingVersion[] $graded_versions
     */
    public function setAutogradingVersions(array $graded_versions) {
        foreach ($graded_versions as $graded_version) {
            if (!($graded_version instanceof AutogradingVersion)) {
                throw new \InvalidArgumentException('Graded Version array contained invalid type');
            }
        }
        $this->graded_versions = $graded_versions;
    }

    /**
     * Sets the date that the user viewed their grade
     * @param string|\DateTime $user_viewed_date The date or date string of when the user viewed their grade
     * @throws \Exception if $grade_time is a string and failed to parse into a \DateTime object
     */
    public function setUserViewedDate($user_viewed_date) {
        if ($user_viewed_date === null) {
            $this->user_viewed_date = null;
        } else {
            $this->user_viewed_date = DateUtils::parseDateTime($user_viewed_date, $this->core->getConfig()->getTimezone());
        }
    }

    /**
     * Sets the id of this grade data
     * @param int $id
     */
    private function setIdInternal($id) {
        if (is_int($id) || ctype_digit($id) && intval($id) >= 0) {
            $this->id = intval($id);
        } else {
            throw new \InvalidArgumentException('Id must be a non-negative integer');
        }
    }

    /**
     * Sets the active submission version for this electronic gradeable
     * @param int $version
     */
    public function setActiveVersion($version) {
        if (is_int($version) || ctype_digit($version) && intval($version) >= 0) {
            $this->active_version = intval($version);
        } else {
            throw new \InvalidArgumentException('Active version must be a non-negative integer');
        }
    }

    /* Intentionally Unimplemented accessor methods */

    /** @internal */
    public function setId($id) {
        throw new \BadFunctionCallException('Cannot set id of gradeable data');
    }

    /** @internal */
    public function setGradeableId($id) {
        throw new \BadFunctionCallException('Cannot set id of gradeable associated with gradeable data');
    }

    /** @internal */
    public function setSubmitter(Submitter $submitter) {
        throw new \BadFunctionCallException('Cannot set gradeable submitter');
    }
}
