<?php

namespace app\models\gradeable;

use app\libraries\Core;
use app\models\AbstractModel;
use app\models\User;

/**
 * Class GradedGradeable
 * @package app\models\gradeable
 *
 * @method string getGradeableId()
 * @method AutoGradedGradeable getAutoGradedGradeable()
 * @method TaGradedGradeable|null getTaGradedGradeable()
 * @method array|null getGradeInquiries()
 * @method Submitter getSubmitter()
 * @method array getLateDayExceptions()
 */
class GradedGradeable extends AbstractModel {
    /** @var Gradeable Reference to gradeable */
    private $gradeable;
    /** @prop
     * @var string Id of the gradeable this grade is attached to */
    protected $gradeable_id;

    /** @prop
     * @var Submitter The submitter who received this graded gradeable */
    protected $submitter;
    /** @prop
     * @var TaGradedGradeable|null The TA Grading info or null if it doesn't exist  */
    protected $ta_graded_gradeable = null;
    /** @prop
     * @var AutoGradedGradeable The Autograding info */
    protected $auto_graded_gradeable = null;
    /** @prop
     * @var array The grade inquiries for this submitter/gradeable  */
    protected $grade_inquiries = [];

    /** @prop
     * @var array The late day exceptions indexed by user id */
    protected $late_day_exceptions;

    /** @prop
     * @var array<string> The reasons for exceptions indexed by user id */
    protected $reasons_for_exceptions;

    /** @prop
     * @var bool|null|SimpleGradeOverriddenUser Does this graded gradeable have overridden grades */
    protected $overridden_grades = false;

    /** @prop
     * @var array<array<int, string>> The active graders for this graded gradeable */
    protected $active_graders_names;

    /** @prop
     * @var array<array<int, string>> The active graders for this graded gradeable */
    protected $active_graders;

    /** @prop
     * @var array<array<int, string>> The timestamps for the active graders this graded gradeable */
    protected $active_graders_timestamps;

    /**
     * GradedGradeable constructor.
     * @param Core $core
     * @param Gradeable $gradeable The gradeable associated with this grade
     * @param Submitter $submitter The user or team who submitted for this graded gradeable
     * @param array $details Other construction details (indexed by property name)
     * @param array<array<int, string>> $active_graders The active graders for this graded gradeable
     * @param array<array<int, string>> $active_graders_timestamps The timestamps for the active graders this graded gradeable
     * @param array<array<int, string>> $active_graders_names The names for the active graders this graded gradeable
     * @throws \InvalidArgumentException If the provided gradeable or submitter are null
     */
    public function __construct(Core $core, Gradeable $gradeable, Submitter $submitter, array $details, array $active_graders = [], array $active_graders_timestamps = [], array $active_graders_names = []) {
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

        $this->late_day_exceptions = $details['late_day_exceptions'] ?? [];

        $this->reasons_for_exceptions = $details['reasons_for_exceptions'] ?? [];

        $this->active_graders = $active_graders;
        $this->active_graders_timestamps = $active_graders_timestamps;
        $this->active_graders_names = $active_graders_names;
    }

    /**
     * Gets the active graders for this graded gradeable
     * @return array<array<int, string>>
     */
    public function getActiveGraders(): array {
        return $this->active_graders;
    }

    /**
     * Gets the active graders timestamps for this graded gradeable
     * @return array<array<int, string>>
     */
    public function getActiveGradersTimestamps(): array {
        return $this->active_graders_timestamps;
    }

    /**
     * Gets the active graders names for this graded gradeable
     * @return array<array<int, string>>
     */
    public function getActiveGradersNames(): array {
        return $this->active_graders_names;
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
     */
    public function hasTaGradingInfo(): bool {
        return $this->ta_graded_gradeable !== null && $this->ta_graded_gradeable->anyGrades();
    }

    /**
     * Gets whether the TA grading has been completed for this submitter/gradeable
     */
    public function isTaGradingComplete(): bool {
        return $this->hasTaGradingInfo() && $this->ta_graded_gradeable->isComplete();
    }

    /**
     * Gets whether a peer grader has graded all of the peer components for this submitter/gradeable
     * Later this will take in a userId and determine if that user graded all components
     * @param User|null $grader Peer grader to check if all peer components associated with this grader has been graded.
     */
    public function isPeerGradingComplete(User $grader = null): bool {
        foreach ($this->ta_graded_gradeable->getGradedComponentContainers() as $container) {
            if (!$container->isComplete($grader) && $container->getComponent() != null && $container->getComponent()->isPeerComponent()) {
                return false;
            }
        }
        return true;
    }


    /**
     * Sets the grade inquiry for this graded gradeable
     * @param array $grade_inquiries
     */
    public function setGradeInquiries(array $grade_inquiries) {
        $this->grade_inquiries = $grade_inquiries;
    }

    /**
     * Gets if the submitter has a grade inquiry
     * @return bool
     */
    public function hasGradeInquiry() {
        return $this->grade_inquiries !== null && count($this->grade_inquiries) > 0;
    }

    /**
     * Gets if the submitter has an active grade inquiry
     * @return bool
     */
    public function hasActiveGradeInquiry() {
        return $this->hasGradeInquiry() &&
            array_reduce($this->grade_inquiries, function ($carry, GradeInquiry $grade_inquiry) {
                if ($this->gradeable->isGradeInquiryPerComponentAllowed()) {
                    $carry = $grade_inquiry->getStatus() == GradeInquiry::STATUS_ACTIVE || $carry;
                }
                else {
                    $carry = $grade_inquiry->getStatus() == GradeInquiry::STATUS_ACTIVE && is_null($grade_inquiry->getGcId()) || $carry;
                }

                return $carry;
            });
    }

    /**
     * Gets the grade inquiry assigned to the gradeable's component supplied
     * @param int $gc_id Gradeable Component id
     */
    public function getGradeInquiryByGcId($gc_id) {
        foreach ($this->grade_inquiries as $grade_inquiry) {
            if ($grade_inquiry->getGcId() == $gc_id) {
                return $grade_inquiry;
            }
        }
        return null;
    }

    /**
     * get the number of grade inquiries that are pending
     * @return int
     */
    public function getActiveGradeInquiryCount() {
        if (!$this->gradeable->isGradeInquiryPerComponentAllowed()) {
            return array_reduce($this->grade_inquiries, function ($carry, GradeInquiry $grade_inquiry) {
                return $carry + (is_null($grade_inquiry->getGcId()) && $grade_inquiry->getStatus() == GradeInquiry::STATUS_ACTIVE ? 1 : 0);
            });
        }
        return array_reduce($this->grade_inquiries, function ($carry, GradeInquiry $grade_inquiry) {
            return $carry + ($grade_inquiry->getStatus() == GradeInquiry::STATUS_ACTIVE ? 1 : 0);
        });
    }

    /**
     * get number of grade inquiries pending and resolved for this gradeable
     * @return int
     */
    public function getGradeInquiryCount() {
        return count($this->grade_inquiries);
    }

    /**
     * Gets the late day exception count for a user
     * @param User|null $user The user to get exception info for (can be null if not team assignment)
     * @return int The number of late days the user has for this gradeable
     */
    public function getLateDayException($user = null) {
        if ($user === null) {
            if ($this->gradeable->isTeamAssignment()) {
                throw new \InvalidArgumentException('Must provide user if team assignment');
            }
            return $this->late_day_exceptions[$this->submitter->getId()] ?? 0;
        }
        return $this->late_day_exceptions[$user->getId()] ?? 0;
    }

    /**
     * Gets the reason of exception for a user
     * @param User|null $user The user to get exception info for (can be null if not team assignment)
     * @return string the reason for a user's excused absence extension
     */
    public function getReasonForException(?User $user = null): string {
        if ($user === null) {
            if ($this->gradeable->isTeamAssignment()) {
                throw new \InvalidArgumentException('Must provide user if team assignment');
            }
            return $this->reasons_for_exceptions[$this->submitter->getId()] ?? '';
        }
        return $this->reasons_for_exceptions[$user->getId()] ?? '';
    }

    /**
     * Gets the auto grading score for the active version, or 0 if none
     * @return int
     */
    public function getAutoGradingScore() {
        if ($this->getAutoGradedGradeable()->hasActiveVersion()) {
            return $this->getAutoGradedGradeable()->getActiveVersionInstance()->getTotalPoints();
        }
        return 0;
    }

    /**
     * Gets the ta grading score
     * Note: This does not check any consistency with submission version
     *  and graded version
     * @return float
     */
    public function getTaGradingScore() {
        if ($this->hasTaGradingInfo()) {
            return $this->getTaGradedGradeable()->getTotalScore();
        }
        return 0.0;
    }

    /**
     * Gets the total score for this student's active submission
     * Note: This does not check that the graded version matches
     *      the active version or any other consistency checking
     * @return float max(0.0, auto_score + ta_score)
     */
    public function getTotalScore() {
        if ($this->hasOverriddenGrades()) {
            return floatval(max(0.0, $this->overridden_grades->getMarks()));
        }
        else {
            return floatval(max(0.0, $this->getTaGradingScore() + $this->getAutoGradingScore()));
        }
    }

    public function getOverriddenComment() {
        $overridden_comment = "";
        if ($this->hasOverriddenGrades()) {
            $overridden_comment = $this->overridden_grades->getComment();
        }
        return $overridden_comment;
    }


    public function hasSubmission() {
        return $this->gradeable->hasSubmission($this->submitter);
    }

    public function hasOverriddenGrades() {
        if ($this->overridden_grades === false) {
            $this->overridden_grades = $this->core->getQueries()->getAUserWithOverriddenGrades($this->gradeable_id, $this->submitter->getId());
        }
        return $this->overridden_grades !== null;
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

    /** @internal  */
    public function setLateDayExceptions() {
        throw new \BadFunctionCallException('Cannot set late day exception info');
    }
}
