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

    /** @prop
     * @var int The number of unused late days the user has as of this gradeable, not including exceptions */
    protected $late_days_remaining = null;
    /** @prop
     * @var int The number of late days allowed for this assignment */
    protected $late_days_allowed = null;
    /** @prop
     * @var int The number of days late the current submission is */
    protected $submission_days_late = null;
    /** @prop
     * @var int The number exceptions allowed for the user on this assignment */
    protected $late_day_exceptions = null;
    /** @prop
     * @var string The reason for a given late day exception */
    protected $reason_for_exception = null;
    /** @prop
     * @var int The update to late days remaining based on this late day event */
    protected $late_days_change = null;
    /** @prop
     * @var bool True if the current submission has an active version and has late day info */
    protected $has_late_day_info = null;
    /** @prop
     * @var bool True if the current gradeable has a submission */
    protected $has_submission = null;
    /** @prop
     * @var \DateTime Time of late day event */
    protected $late_day_date = null;
    /** @prop
     * @var string id of the late day event */
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
        $this->late_day_date = $event_info['late_day_date'] ?? null;
        $this->submission_days_late = $event_info['submission_days_late'] ?? null;
        $this->late_day_exceptions = $event_info['late_day_exceptions'] ?? null;
        $this->reason_for_exception = $event_info['reason_for_exception'] ?? null;
        $this->late_days_remaining = $event_info['late_days_remaining']  ?? null;
        $this->late_days_change = $event_info['late_days_change']  ?? null;

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

    /**
     * Create a new LateDay instance for a given user
     * @param Core $core
     * @param User $user
     * @param GradedGradeable $graded_gradeable
     * @param int $late_days_remaining the late days remaining before this gradeable was submitted
     * @return LateDayInfo
     */
    public static function fromGradeableLateDaysRemaining(Core $core, User $user, GradedGradeable $graded_gradeable, int $late_days_remaining) {
        $late_days_allowed = $graded_gradeable->getGradeable()->getLateDays();
        $auto_graded_gradeable = $graded_gradeable->getAutoGradedGradeable();
        $submission_days_late = $auto_graded_gradeable->hasActiveVersion() ? $auto_graded_gradeable->getActiveVersionInstance()->getDaysLate() : 0;
        $exceptions = $graded_gradeable->getLateDayException($user);
        $reason = $graded_gradeable->getReasonForException($user);

        $late_days_charged = 0;
        $assignment_budget = min($late_days_allowed, $late_days_remaining) + $exceptions;
        if ($submission_days_late <= $assignment_budget) {
            // clamp the days charged to be the days late minus exceptions above zero.
            $late_days_charged = max(0, min($submission_days_late, $assignment_budget) - $exceptions);
        }
        $late_days_remaining -= $late_days_charged;

        $event_info = [
            'id' => $graded_gradeable->getGradeableId(),
            'graded_gradeable' => $graded_gradeable,
            'late_days_allowed' => $late_days_allowed,
            'late_day_date' => $graded_gradeable->getGradeable()->getSubmissionDueDate(),
            'submission_days_late' => $submission_days_late,
            'late_day_exceptions' => $exceptions,
            'reason_for_exception' => $reason,
            'late_days_remaining' => $late_days_remaining,
            'late_days_change' => -$late_days_charged
        ];

        return new LateDayInfo($core, $user, $event_info);
    }

    /*
     * Get the Late Day Info for each user associated with a submitter and gradeable
     * @param Core $core
     * @param User $user
     * @param GradedGradeable $graded_gradeable
     * @return LateDayInfo|null
     */
    public static function fromUser(Core $core, User $user, GradedGradeable $graded_gradeable): ?LateDayInfo {
        $ldc = $core->getQueries()->getLateDayCacheForUserGradeable($user->getId(), $graded_gradeable->getGradeableId());
        $ldi = null;

        if ($ldc !== null) {
            $ldi['graded_gradeable'] = $graded_gradeable;
            $ldi = new LateDayInfo($core, $user, $ldc);
        }

        return $ldi;
    }

    /**
     * Get the Late Day Info for each user associated with a submitter and gradeable
     * @param Core $core
     * @param Submitter $submitter
     * @param GradedGradeable $graded_gradeable
     * @return LateDayInfo|array
     */
    public static function fromSubmitter(Core $core, Submitter $submitter, $graded_gradeable) {
        // Collect Late Day Info for each user associated with the submitter
        if ($submitter->isTeam()) {
            $late_day_info = [];
            foreach ($submitter->getTeam()->getMemberUsers() as $member) {
                $late_day_info[$member->getId()] = self::fromUser($core, $member, $graded_gradeable);
            }
            return $late_day_info;
        }
        else {
            return self::fromUser($core, $submitter->getUser(), $graded_gradeable);
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
            'reason_for_exception' => $this->getReasonForException(),
            'late_day_status' => $this->getStatus(),
            'late_days_change' => $this->getLateDaysChange()
        ];
    }

    /**
     * Gets if the submitter submitted on time
     * @return bool
     */
    public function isOnTimeSubmission() {
        // if there is no submission or if this isn't a gradeable event, ignore
        if (!$this->has_submission || $this->isLateDayUpdate()) {
            return true;
        }

        return $this->getStatus() == self::STATUS_GOOD || $this->getStatus() == self::STATUS_LATE;
    }

    /**
     * Get g_id for the late day event
     * @return string|null
     */
    public function getGradeableId(): ?string {
        return $this->isLateDayUpdate() ? null : $this->graded_gradeable->getGradeableId();
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
     * Gets the reason for an excused absence extension
     */
    public function getReasonForException(): string {
        return $this->reason_for_exception;
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
     * Returns true if this event is a graded gradeable charge with grade inquiries allowed
     * @return bool
     */
    public function isGradeInquiryAllowed() {
        if ($this->graded_gradeable === null) {
            return false;
        }
        return $this->graded_gradeable->getGradeable()->isGradeInquiryAllowed();
    }

    /**
     * Get number of grade inquiries pending and resolved for this gradeable
     * @return int
     */
    public function getGradeInquiryCount() {
        return $this->graded_gradeable !== null ? $this->graded_gradeable->getGradeInquiryCount() : null;
    }
}
