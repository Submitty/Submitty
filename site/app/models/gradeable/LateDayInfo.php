<?php

namespace app\models\gradeable;


use app\libraries\Core;
use app\models\AbstractModel;
use app\models\User;

/**
 * Class LateDayInfo
 * @package app\models\gradeable
 *
 * Late day calculation per graded gradeable (per user)
 *
 * @method int getLateDaysRemaining()
 * @method int getCumulativeLateDaysUsed()
 */
class LateDayInfo extends AbstractModel {

    const STATUS_NO_ACTIVE_VERSION = 0;
    const STATUS_GOOD = 1;
    const STATUS_LATE = 2;
    const STATUS_BAD = 3;

    public static function isValidStatus($status) {
        return in_array($status, [self::STATUS_GOOD, self::STATUS_LATE, self::STATUS_BAD]);
    }

    /** @var GradedGradeable */
    private $graded_gradeable = null;
    /** @var User */
    private $user = null;

    /** @property @var int The number of unused late days the user has as of this gradeable, not including exceptions */
    protected $late_days_remaining = null;

    /**
     * LateDayInfo constructor.
     * @param Core $core
     * @param User $user
     * @param GradedGradeable $graded_gradeable
     * @param int $late_days_remaining The number of late days remaining for use as of the time of this gradeable
     */
    public function __construct(Core $core, User $user, GradedGradeable $graded_gradeable, int $late_days_remaining) {
        parent::__construct($core);
        if (!$graded_gradeable->getSubmitter()->hasUser($user)) {
            throw new \InvalidArgumentException('Provided user did not match provided GradedGradeable');
        }

        $this->user = $user;
        $this->graded_gradeable = $graded_gradeable;

        // Get the late days available as of this gradeable's due date
        if ($late_days_remaining < 0) {
            throw new \InvalidArgumentException('Late days remaining must be at least 0');
        }
        $this->late_days_remaining = $late_days_remaining;
    }

    public function toArray() {
        return [
            'gradeable_title' => $this->graded_gradeable->getGradeable()->getTitle(),
            'submission_due_date' => $this->graded_gradeable->getGradeable()->getSubmissionDueDate()->format('m/d/y'),
            'g_allowed_late_days' => $this->graded_gradeable->getGradeable()->getLateDays(),
            'exceptions' => $this->getLateDayException(),
            'status' => $this->getStatus(),
            'late_days_remaining' => $this->late_days_remaining,
            'days_late' => $this->hasLateDaysInfo() ? $this->getDaysLate() : null,
            'charged_late_days' => $this->hasLateDaysInfo() ? $this->getLateDaysCharged() : null
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
        return min($this->graded_gradeable->getGradeable()->getLateDays(), $this->late_days_remaining) + $this->getLateDayException();
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
     * @return int One of self::STATUS_NO_ACTIVE_VERSION, self::STATUS_BAD, self::STATUS_LATE, or self::STATUS_GOOD
     */
    public function getStatus() {
        // No late days info, so NO_SUBMISSION
        if (!$this->hasLateDaysInfo()) {
            return self::STATUS_NO_ACTIVE_VERSION;
        }

        // If the number of days late is more than the number allowed, then its BAD
        if ($this->getDaysLate() > $this->getLateDaysAllowed()) {
            return self::STATUS_BAD;
        }

        // if the student submitted after the deadline (plus extensions) then its late
        if ($this->getDaysLate() > $this->getLateDayException()) {
            return self::STATUS_LATE;
        }

        // ... otherwise, its GOOD
        return self::STATUS_GOOD;
    }

    /**
     * Gets the status message for this gradeable
     * @return string
     */
    public function getStatusMessage() {
        switch ($this->getStatus()) {
            case self::STATUS_NO_ACTIVE_VERSION:
                if ($this->graded_gradeable->getAutoGradedGradeable()->hasSubmission()) {
                    return 'Cancelled Submission';
                } else {
                    return 'No Submission';
                }
            case self::STATUS_GOOD:
                return 'Good';
            case self::STATUS_LATE:
                return 'Late';
            case self::STATUS_BAD:
                $days_late = $this->getDaysLate();
                if ($days_late > $this->late_days_remaining) {
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
        if ($this->getStatus() === self::STATUS_BAD) {
            // Don't charge late days for BAD status
            return 0;
        }
        // clamp the days charged to be the days late minus exceptions above zero.
        return max(0, min($this->getDaysLate(), $this->getLateDaysAllowed()) - $this->getLateDayException());
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
}