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
 * @method AutoGradedGradeable getAutoGradedGradeable()
 * @method TaGradedGradeable|null getTaGradedGradeable()
 * @method Submitter getSubmitter()
 */
class GradedGradeable extends AbstractModel {
    /** @var Gradeable Reference to gradeable */
    private $gradeable = null;
    /** @property @var string Id of the gradeable this grade is attached to */
    protected $gradeable_id = "";

    /** @property @var Submitter The submitter who received this graded gradeable */
    protected $submitter = null;
    /** @property @var TaGradedGradeable|null The TA Grading info or null if it doesn't exist  */
    protected $ta_graded_gradeable = null;
    /** @property @var AutoGradedGradeable The Autograding info */
    protected $auto_graded_gradeable = null;


    /**
     * GradedGradeable constructor.
     * @param Core $core
     * @param Gradeable $gradeable The gradeable associated with this grade
     * @param Submitter $submitter The user or team who submitted for this graded gradeable
     * @throws \InvalidArgumentException If the provided gradeable or submitter are null
     */
    public function __construct(Core $core, Gradeable $gradeable, Submitter $submitter) {
        parent::__construct($core);

        // Check the gradeable instance
        if ($gradeable === null) {
            throw new \InvalidArgumentException('Gradeable cannot be null');
        }
        $this->gradeable = $gradeable;
        $this->gradeable_id = $gradeable->getId();

        // Check the Submitter instance
        if ($submitter === null) {
            throw new \InvalidArgumentException('Submitter cannot be null');
        }
        $this->submitter = $submitter;
    }

    /**
     * Gets the gradeable this grade data is associated with
     * @return Gradeable the gradeable this grade data is associated with
     */
    public function getGradeable() {
        return $this->gradeable;
    }

    /**
     * Sets the TA grading data for this graded gradeable
     * @param TaGradedGradeable $ta_graded_gradeable
     */
    public function setTaGradedGradeable(TaGradedGradeable $ta_graded_gradeable) {
        $this->ta_graded_gradeable = $ta_graded_gradeable;
    }

    /**
     * Gets the TaGradedGradeable for this graded gradeable, or generates a blank
     *  one if none exists
     * @return TaGradedGradeable|null
     */
    public function getOrCreateTaGradedGradeable() {
        if ($this->ta_graded_gradeable === null) {
            $this->ta_graded_gradeable = new TaGradedGradeable($this->core, $this, []);
        }
        return $this->ta_graded_gradeable;
    }

    /**
     * Sets the Autograding data for this graded gradeable
     * @param AutoGradedGradeable $auto_graded_gradeable
     */
    public function setAutoGradedGradeable(AutoGradedGradeable $auto_graded_gradeable) {
        $this->auto_graded_gradeable = $auto_graded_gradeable;
    }

    /**
     * Gets whether any TA grading information exists for this submitter/gradeable
     * @return bool
     */
    public function hasTaGradingInfo() {
        return $this->ta_graded_gradeable !== null;
    }

    /**
     * Gets whether the TA grading has been completed for this submitter/gradeable
     * @return bool
     */
    public function isTaGradingComplete() {
        return $this->hasTaGradingInfo() && $this->ta_graded_gradeable->getPercentGraded() == 1;
    }

    /* Intentionally Unimplemented accessor methods */

    /** @internal */
    public function setGradeableId($id) {
        throw new \BadFunctionCallException('Cannot set id of gradeable associated with gradeable data');
    }

    /** @internal */
    public function setSubmitter(Submitter $submitter) {
        throw new \BadFunctionCallException('Cannot set gradeable submitter');
    }
}
