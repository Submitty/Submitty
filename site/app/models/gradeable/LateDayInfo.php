<?php

namespace app\models\gradeable;


use app\controllers\student\LateDaysTableController;
use app\libraries\Core;
use app\libraries\DateUtils;
use app\models\AbstractModel;
use app\models\User;

/**
 * Class LateDayInfo
 * @package app\models\gradeable
 *
 * Late day calculation per graded gradeable (per user)
 *
 * @method int getLateDaysAvailable()
 */
class LateDayInfo extends AbstractModel {

    /** @var GradedGradeable */
    private $graded_gradeable = null;
    /** @var User */
    private $user = null;

    /** @property @var int The number of unused late days the user has as of this gradeable, not including exceptions */
    protected $late_days_available = null;

    /** @var int|null The number of late days used by previous gradeables */
    private $cumulative_late_days_used = null;

    /**
     * LateDayInfo constructor.
     * @param Core $core
     * @param User $user
     * @param GradedGradeable $graded_gradeable
     * @param int $cumulative_late_days_used Number of late days used by other gradeables
     * @param int $late_days_available The number of late days available for use as of the time of this gradeable
     */
    public function __construct(Core $core, User $user, GradedGradeable $graded_gradeable, int $cumulative_late_days_used, int $late_days_available) {
        parent::__construct($core);
        if (!$graded_gradeable->getSubmitter()->hasUser($user)) {
            throw new \InvalidArgumentException('Provided user did not match provided GradedGradeable');
        }

        $this->user = $user;
        $this->graded_gradeable = $graded_gradeable;

        // Get the late days available as of this gradeable's due date
        if($late_days_available < 0) {
            throw new \InvalidArgumentException('Late days available must be at least 0');
        }
        $this->late_days_available = $late_days_available;

        if($cumulative_late_days_used < 0) {
            throw new \InvalidArgumentException('Late days used must be at least 0');
        }
        $this->cumulative_late_days_used = $cumulative_late_days_used;
    }

    public function toArray() {
        return [
            'gradeable_title' => $this->graded_gradeable->getGradeable()->getTitle(),
            'submission_due_date' => $this->graded_gradeable->getGradeable()->getSubmissionDueDate()->format('m/d/y'),
            'g_allowed_late_days' => $this->graded_gradeable->getGradeable()->getLateDays(),
            'exceptions' => $this->getLateDayExceptions(),
            'status' => $this->getStatus(),
            'late_days_available' => $this->late_days_available,
            'days_late' => $this->hasLateDaysInfo() ? $this->getDaysLate() : null,
            'charged_late_days' => $this->hasLateDaysInfo()? $this->getLateDaysCharged() : null
        ];
    }

    /**
     * Gets the GradedGradeable associated with this late day info
     * @return GradedGradeable
     */
    public function getGradedGradeable() {
        return $this->graded_gradeable;
    }

    /**
     * Gets the number of days late the user may submit this gradeable and not be STATUS_BAD
     * @return int
     */
    public function getLateDaysAllowed() {
        return min($this->graded_gradeable->getGradeable()->getLateDays(), $this->late_days_available) + $this->getLateDayExceptions();
    }

    /**
     * Gets the number of late days the student gets extra for this gradeable
     * @return int
     */
    public function getLateDayException() {
        return $this->getGradedGradeable()->getLateDayException($this->user);
    }

    /**
     * Gets the late status of the gradeable
     * @return int One of LateDays::STATUS_NO_SUBMISSION, LateDays::STATUS_BAD, LateDays::STATUS_LATE, or LateDays::STATUS_GOOD
     */
    public function getStatus() {
        // No late days info, so NO_SUBMISSION
        if(!$this->hasLateDaysInfo()) {
            return LateDays::STATUS_NO_SUBMISSION;
        }

        $days_late = $this->getDaysLate();
        // If the number of days late is more than the minimum of: the max for this gradeable and the days the user has
        //  left, then this is a BAD status
        if ($days_late > $this->getLateDaysAllowed()) {
            return LateDays::STATUS_BAD;
        }

        // If the number of days late is more 0, it is LATE
        if ($days_late > 0) {
            return LateDays::STATUS_LATE;
        }

        // ... otherwise, its GOOD
        return LateDays::STATUS_GOOD;
    }

    /**
     * Gets the status message for this gradeable
     * @return string
     * @throws \Exception
     */
    public function getStatusMessage() {
        switch ($this->getStatus()) {
            case LateDays::STATUS_NO_SUBMISSION:
                return 'No Submission';
            case LateDays::STATUS_GOOD:
                return 'Good';
            case LateDays::STATUS_LATE:
                return 'Late';
            case LateDays::STATUS_BAD:
                $days_late = $this->getDaysLate();
                if ($days_late > $this->late_days_available) {
                    return 'Bad (too many late days used this term)';
                } else {
                    return 'Bad (too many late days used on this assignment)';
                }
            default:
                return 'INTERNAL ERROR';
        }
    }

    /**
     * Gets if this user has late days info available (if they have an active version)
     * @return bool
     */
    public function hasLateDaysInfo() {
        return $this->graded_gradeable->getAutoGradedGradeable()->hasActiveVersion();
    }

    /**
     * Gets the number of late days charged for this assignment
     * @return int
     */
    public function getLateDaysCharged() {
        if ($this->getStatus() === LateDays::STATUS_BAD) {
            // Don't charge late days for BAD status
            return 0;
        }
        return min($this->getDaysLate() - $this->getLateDayExceptions(), $this->getLateDaysAllowed());
    }

    /**
     * Gets the number of days late for the active version
     * @return int
     */
    public function getDaysLate() {
        if (!$this->hasLateDaysInfo()) {
            return 0;
        }
        return $this->graded_gradeable->getAutoGradedGradeable()->getActiveVersionInstance()->getDaysLate();
    }

    /**
     * Gets the late day exception for this gradeable and user
     * @return int
     */
    public function getLateDayExceptions() {
        return $this->graded_gradeable->getLateDayException($this->user);
    }
}