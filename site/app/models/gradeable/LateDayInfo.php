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
 * @method string getId()
 */
class LateDayInfo extends AbstractModel {
    const STATUS_NO_ACTIVE_VERSION = 0;
    const STATUS_GOOD = 1;
    const STATUS_LATE = 2;
    const STATUS_BAD = 3;

    public static function isValidStatus(int $status): bool {
        return in_array($status, [self::STATUS_GOOD, self::STATUS_LATE, self::STATUS_BAD]);
    }

    /** @var GradedGradeable */
    private $graded_gradeable = null;
    /** @var User */
    private $user = null;

    /** @prop @var int The number of unused late days the user has as of this gradeable, not including exceptions */
    protected $late_days_remaining = null;
    /** @prop @var int The number of late days allowed for this assignment */
    protected $late_days_allowed = null;
    /** @prop @var int The number of days late the current submission is */
    protected $submission_days_late = null;
    /** @prop @var int The number exceptions allowed for the user on this assignment */
    protected $late_day_exceptions = null;
    /** @prop @var int The update to late days remaining based on this late day event */
    protected $late_days_change = null;
    /** @prop @var bool True if the current submission has an active version and has late day info */
    protected $has_late_day_info = null;
    /** @prop @var bool True if the current gradeable has a submission */
    protected $has_submission = null;
    /** @prop @var \DateTime Time of late day event */
    protected $late_day_date = null;
    /** @prop @var string id of the late day event */
    protected $id = null;

    /**
     * LateDayInfo constructor.
     * @param Core $core
     * @param User $user
     * @param array $event_info The information for the given graded gradeable or late day update
     */
    public function __construct(Core $core, User $user, array $event_info) {
        parent::__construct($core);

        $this->user = $user;

        $this->id = $event_info['id'] ?? null;
        $this->graded_gradeable = $event_info['graded_gradeable'] ?? null;
        $this->late_days_allowed = $event_info['late_days_allowed'] ?? null;
        $this->late_day_date = $event_info['late_day_date'];
        $this->submission_days_late = $event_info['submission_days_late'] ?? null;
        $this->late_day_exceptions = $event_info['late_day_exceptions'] ?? null;
        $this->late_days_remaining = $event_info['late_days_remaining'];
        $this->late_days_change = $event_info['late_days_change'];

        // Set Autograded gradeable info
        $auto_graded_gradeable = $this->graded_gradeable !== null ? $this->graded_gradeable->getAutoGradedGradeable() : null;
        $this->has_late_day_info = $auto_graded_gradeable !== null ? $auto_graded_gradeable->hasActiveVersion() : null;
        $this->has_submission = $auto_graded_gradeable !== null ? $auto_graded_gradeable->hasSubmission() : null;

        if ($this->graded_gradeable !== null && !$this->graded_gradeable->getSubmitter()->hasUser($user)) {
            throw new \InvalidArgumentException('Provided user did not match provided GradedGradeable');
        }

        // Get the late days available as of this gradeable's due date
        if ($this->late_days_remaining < 0) {
            throw new \InvalidArgumentException('Late days remaining must be at least 0');
        }
    }

    public function toArray() {
        return [
            'gradeable_title' => $this->graded_gradeable->getGradeable()->getTitle(),
            'submission_due_date' => $this->graded_gradeable->getGradeable()->hasDueDate() ? $this->graded_gradeable->getGradeable()->getSubmissionDueDate()->format('m/d/y') : null,
            'g_allowed_late_days' => $this->graded_gradeable->getGradeable()->getLateDays(),
            'exceptions' => $this->getLateDayException(),
            'status' => $this->getStatus(),
            'late_days_remaining' => $this->late_days_remaining,
            'days_late' => $this->hasLateDaysInfo() ? $this->getDaysLate() : null,
            'charged_late_days' => $this->hasLateDaysInfo() ? $this->getLateDaysCharged() : null,
            'grade_inquiries' => $this->graded_gradeable->getGradeInquiryCount()
        ];
    }

    /**
     * Gets information about this late day event in the format for the cache table
     * @return array
     */
    public function generateEventInfo(): array {
        return [
            'g_id' => $this->isLateDayUpdate() ? null : $this->getId(),
            'user_id' => $this->user->getId(),
            'team_id' => null,
            'g_title' => $this->isLateDayUpdate() ? null : $this->getEventTitle(),
            'late_day_date' => $this->getLateDayEventTime()->format('Y-m-d H:i:s'),
            'late_days_remaining' => $this->getLateDaysRemaining(),
            'late_days_allowed' => $this->getAssignmentAllowedLateDays(),
            'submission_days_late' => $this->getDaysLate(),
            'late_day_exceptions' => $this->getLateDayException(),
            'late_day_status' => $this->getStatus(),
            'late_days_change' => $this->getLateDaysChange()
        ];
    }

    /**
     * Gets the time the late day event took place
     * @return \DateTime
     */
    public function getLateDayEventTime() {
        return $this->late_day_date;
    }

    /**
     * Returns true if the late day event is a late day update
     * (no associated graded gradeable)
     * @return boolean
     */
    public function isLateDayUpdate() {
        return $this->graded_gradeable === null;
    }

    /**
     * Gets the event title for this event
     * @return string
     */
    public function getEventTitle() {
        return $this->isLateDayUpdate() ? '' : $this->graded_gradeable->getGradeable()->getTitle();
    }

    /**
     * Gets the GradedGradeable associated with this late day info
     * @return GradedGradeable
     */
    public function getGradedGradeable() {
        return $this->graded_gradeable;
    }

    /**
     * Gets the max number of late days the instructor allows for the gradeable
     * @return int
     */
    public function getAssignmentAllowedLateDays() {
        return $this->late_days_allowed;
    }

    /**
     * Gets the number of days late the user may submit this gradeable and not be STATUS_BAD
     * @return int
     */
    public function getLateDaysAllowed() {
        return min($this->getAssignmentAllowedLateDays(), $this->late_days_remaining) + $this->getLateDayException();
    }

    /**
     * Gets the number of late days the student gets extra for this gradeable
     * @return int
     */
    public function getLateDayException() {
        return $this->late_day_exceptions;
    }

    /**
     * Gets the late status of the gradeable
     * @param int $days_late optional - calculate the late day status based on if the gradeable used $days_late late days
     * @return int One of self::STATUS_NO_ACTIVE_VERSION, self::STATUS_BAD, self::STATUS_LATE, or self::STATUS_GOOD
     */
    public function getStatus(int $days_late = null) {
        // No late days info, so NO_SUBMISSION
        if (!$this->hasLateDaysInfo()) {
            return self::STATUS_NO_ACTIVE_VERSION;
        }

        $days = $days_late !== null ? $days_late : $this->getDaysLate();

        // if the student submitted after the deadline (plus extensions),
        // and there were no late days charged, then its bad
        if ($days > $this->getLateDayException() && $this->getLateDaysCharged() <= 0) {
            return self::STATUS_BAD;
        }

        // if the student submitted after the deadline (plus extensions) then its late
        // Note: Late days were charged
        if ($days > $this->getLateDayException()) {
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
                if ($this->has_submission) {
                    return 'Cancelled Submission';
                }
                else {
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
                }
                else {
                    return 'Bad (too many late days used on this assignment)';
                }
            default:
                return 'INTERNAL ERROR';
        }
    }

    /**
     * Gets the status messages for this gradeable
     * @return array
     */
    public static function getSimpleMessageFromSatus() {
        return [
            self::STATUS_NO_ACTIVE_VERSION => 'No Submission',
            self::STATUS_GOOD => 'Good',
            self::STATUS_LATE => 'Late',
            self::STATUS_BAD => 'Bad'
        ];
    }

    /**
     * Gets if this user has late days info available (if they have an active version)
     * @return bool
     */
    public function hasLateDaysInfo() {
        return $this->has_late_day_info;
    }

    /**
     * Gets the number of late days charged from this event (capped at zero)
     * @return int
     */
    public function getLateDaysCharged() {
        return max(-$this->late_days_change, 0);
    }

    /**
     * Gets the number of late days increased from this event
     * @return int
     */
    public function getLateDaysChange() {
        return $this->late_days_change;
    }

    /**
     * Gets the number of days late for the active version
     * @return int
     */
    public function getDaysLate() {
        return $this->submission_days_late;
    }

    /**
     * Returns true if this event is a graded gradeable charge with regrades allowed
     * @return bool
     */
    public function isRegradeAllowed() {
        if ($this->graded_gradable === null) {
            return false;
        }
        return $this->graded_gradeable->getGradeable()->isRegradeAllowed();
    }

    /**
     * Get number of grade inquiries pending and resolved for this gradeable
     * @return int
     */
    public function getGradeInquiryCount() {
        return $this->graded_gradeable !== null ? $this->graded_gradeable->getGradeInquiryCount() : null;
    }
}
