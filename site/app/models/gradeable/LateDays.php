<?php

namespace app\models\gradeable;

use app\libraries\Core;
use app\libraries\GradeableType;
use app\models\AbstractModel;
use app\models\User;

/**
 * Class LateDays
 * @package app\models\gradeable
 *
 * Late day calculation model per user
 *
 * @method LateDayInfo[] getLateDayInfo()
 * @method array getLateDaysUpdates()
 */
class LateDays extends AbstractModel {
    /** @var User|null The user to whom this data belongs */
    private $user = null;
    /** @prop
     * @var LateDayInfo[] The late day info of each gradeable, indexed by gradeable id */
    protected $late_day_info = [];
    /** @prop
     * @var array All entries for the user in the `late_days` table */
    protected $late_days_updates = [];

    /**
     * LateDays constructor.
     * NOTE: use LateDays::fromUser if you want to use default gradeable filtering behavior
     * @param Core $core
     * @param User $user
     * @param GradedGradeable[] $graded_gradeables An array of only GradedGradeables
     * @param bool $reCache
     * @param array|null $late_day_updates
     */
    public function __construct(Core $core, User $user, array $graded_gradeables, $late_day_updates = null, $reCache = false) {
        parent::__construct($core);
        $this->user = $user;

        // Filter out non-electronic gradeables
        $graded_gradeables = array_filter($graded_gradeables, function (GradedGradeable $gg) {
            return $gg->getGradeable()->getType() === GradeableType::ELECTRONIC_FILE;
        });

        // Get the late day updates that the instructor will enter
        $this->late_days_updates = $late_day_updates ?? $this->core->getQueries()->getLateDayUpdates($user->getId());
        $late_day_cache = $this->core->getQueries()->getLateDayCacheForUser($user->getId());
        // Get all late day events (late day updates and graded gradeable submission dates)
        $late_day_events = $this->createLateDayEvents($graded_gradeables);

        $prev_late_days_available = $this->core->getConfig()->getDefaultStudentLateDays();
        $prev_time_stamp = null;
        $late_days_charged = 0;
        $useCache = true;

        // Construct late days info for each gradeable
        $index = 1;
        foreach ($late_day_events as $event) {
            // Use index # for late day update
            if (isset($event['update'])) {
                $id = $index++;
            }
            else {
                $id = $event['gg']->getGradeableId();
            }

            // Grab cache if $useCache is true
            $gradeable_cache = $late_day_cache[$id] ?? null;

            // Recalculate if cache not available
            if (!$useCache || $gradeable_cache == null) {
                // Set use cache to false since all future late day info is now stale
                $useCache = false;
                $info = $this->getLateDayInfoFromPrevious($prev_late_days_available, $event);
            }
            else {
                $event_info = $gradeable_cache;

                // Set gg info for cache
                if (isset($event['gg'])) {
                    $graded_gradeable = $event['gg'];
                    $auto_graded_gradeable = $graded_gradeable->getAutoGradedGradeable();
                    $event_info['graded_gradeable'] = $graded_gradeable;
                }
                $info = new LateDayInfo($core, $user, $event_info);
            }

            // Set previous value
            $prev_late_days_available = $info->getLateDaysRemaining();

            $this->late_day_info[$id] = $info;
            // If the cache wasn't used, the value has been updated
            if (!$useCache && ($reCache)) {
                $this->core->getQueries()->addLateDayCacheForUser($user, $info);
            }
        }
    }

    /**
     * Sort the graded gradeables and late day updates by due date
     * @param GradedGradeable[] $graded_gradeables
     * @return array<array<string, mixed>>|array<array<string, DateTime>>
     */
    private function createLateDayEvents($graded_gradeables) {
        $late_day_events = array_merge(
            array_map(function (GradedGradeable $gg) {
                return [
                    'timestamp' => $gg->getGradeable()->getSubmissionDueDate(),
                    'gg' => $gg
                ];
            }, $graded_gradeables),
            array_map(
                function ($update) {
                    return [
                        'timestamp' => $update['since_timestamp'],
                        'update' => $update
                    ];
                },
                $this->late_days_updates
            )
        );

        // Sort by 'timestamp'
        usort($late_day_events, function ($e1, $e2) {
            $diff = 0;
            if ($e1['timestamp'] !== null && $e2['timestamp'] !== null) {
                $diff = $e1['timestamp']->getTimestamp() - $e2['timestamp']->getTimestamp();

                if ($diff === 0) {
                    if (isset($e1['update'])) { // $e1 is a late day update, higher priority
                        $diff = -1;
                    }
                    elseif (isset($e2['update'])) { // $e2 is a late day update, higher priority
                        $diff = 1;
                    }
                    else { // $e1 and $e2 are ggs, use g_id
                        $diff = strcmp($e1['gg']->getGradeableId(), $e2['gg']->getGradeableId());
                    }
                }
            }
            elseif ($e2['timestamp'] !== null) {
                $diff = 1;
            }
            elseif ($e1['timestamp'] !== null) {
                $diff = -1;
            }
            return $diff;
        });

        return $late_day_events;
    }

    /**
     * Test if the current user is allowed to view late day info for this gradeable
     * @param Core $core
     * @param Gradeable $gradeable
     * @return bool True if they are
     */
    public static function filterCanView(Core $core, Gradeable $gradeable) {
        if ($gradeable->getType() !== GradeableType::ELECTRONIC_FILE) {
            return false;
        }

        // Don't show the students gradeables they don't submit for / don't have due dates
        if (!$gradeable->isStudentSubmit() || !$gradeable->hasDueDate() || !$gradeable->isLateSubmissionAllowed()) {
            return false;
        }

        $user = $core->getUser();

        //Remove incomplete gradeables for non-instructors
        if (!$user->accessAdmin() && !$gradeable->hasAutogradingConfig()) {
            return false;
        }

        // if student view false, never show
        if (!$gradeable->isStudentView() && !$user->accessGrading()) {
            return false;
        }

        //If we're not instructor and this is not open to TAs
        if (!$gradeable->isTaViewOpen() && !$user->accessAdmin()) {
            return false;
        }
        if (!$gradeable->isSubmissionOpen() && !$user->accessGrading()) {
            return false;
        }

        return true;
    }

    /**
     * Create a new LateDay instance for a given user
     * @param Core $core
     * @param User $user
     * @return LateDays
     */
    public static function fromUser(Core $core, User $user) {
        $gradeables = [];
        $graded_gradeables = [];
        // TODO: filter out the gradeable at the QUERY level if possible
        foreach ($core->getQueries()->getGradeableConfigs(null, ['submission_due_date', 'grade_released_date', 'g_id']) as $g) {
            // User the 'core' user since it is the one permission checks are done for
            if (!LateDays::filterCanView($core, $g)) {
                continue;
            }
            $gradeables[] = $g;
        }
        foreach ($core->getQueries()->getGradedGradeables($gradeables, $user->getId()) as $gg) {
            $graded_gradeables[] = $gg;
        }
        return new LateDays($core, $user, $graded_gradeables);
    }

    public function toArray() {
        $details = parent::toArray();

        $details['user_id'] = $this->user->getId();

        return $details;
    }

    /**
     * Gets the user this late day info is for
     * @return User
     */
    public function getUser() {
        return $this->user;
    }

    /**
     * Gets the cumulative number of late days the user has used
     * @return int
     */
    public function getLateDaysUsed() {
        return $this->getLateDaysUsedByContext($this->core->getDateTimeNow());
    }

    /**
     * Gets the cumulative number of late days the user has used at a certain date
     * @param \DateTime $context
     * @return int
     */
    public function getLateDaysUsedByContext(\DateTime $context) {
        $total = 0;
        /** @var LateDayInfo $info */
        foreach ($this->late_day_info as $info) {
            if ($info->getLateDayEventTime() > $context) {
                break;
            }
            $total += $info->isLateDayUpdate() ? 0 : $info->getLateDaysCharged();
        }
        return $total;
    }

    /**
     * Gets the number of late days the students start with (from config)
     * @return int'
     */
    public function getDefaultLateDays() {
        return $this->core->getConfig()->getDefaultStudentLateDays();
    }

    /**
     * Gets the current number of late days remaining
     * @return int
     */
    public function getLateDaysRemaining() {
        // Use 'now' because it is possible that there are changes that occur in the future
        return $this->getLateDaysRemainingByContext($this->core->getDateTimeNow());
    }

    /**
     * Create event information for a given late day event
     * @param array<string,GradedGradeable> $event
     * @param int $late_days_remaining late days remaining after this event took place
     * @param int $late_days_change the increase or decrease of late days from this event
     * @return array<string,int> Information needed in order to construct a LateDayInfo object
     */
    private function createEventInfo($event, $late_days_remaining, $late_days_change) {
        $event_info = [
            'late_days_remaining' => $late_days_remaining,
            'late_days_change' => $late_days_change,
            'late_day_date' => $event['timestamp']
        ];

        if (isset($event['gg'])) {
            $graded_gradeable = $event['gg'];
            $late_days_allowed = $graded_gradeable->getGradeable()->getLateDays();
            $auto_graded_gradeable = $graded_gradeable->getAutoGradedGradeable();
            $submission_days_late = $auto_graded_gradeable->hasActiveVersion() ? $auto_graded_gradeable->getActiveVersionInstance()->getDaysLate() : 0;
            $exceptions = $graded_gradeable->getLateDayException($this->user);
            $reason = $graded_gradeable->getReasonForException($this->user);

            $event_info['graded_gradeable'] = $graded_gradeable;
            $event_info['late_days_allowed'] = $late_days_allowed;
            $event_info['submission_days_late'] = $submission_days_late;
            $event_info['late_day_exceptions'] = $exceptions;
            $event_info['reason_for_exception'] = $reason;
        }

        return $event_info;
    }

    /**
     * Gets the number of late days remaining from the previous number
     * of late days remaining.
     * @param int $prev_late_days_available The number of late days available at the prev time stamp
     * @param array<string, mixed> $event
     * @return LateDayInfo
     */
    public function getLateDayInfoFromPrevious($prev_late_days_available, $event) {
        $late_days_remaining = $prev_late_days_available;
        $late_days_change = 0;

        if (isset($event['gg'])) { // Create Late Day Info for Gradeable
            $info = LateDayInfo::fromGradeableLateDaysRemaining($this->core, $this->user, $event['gg'], $prev_late_days_available);
        }
        else { // Process Late Day Info for Late Day Update
            $new_late_days_available = $event['update']['allowed_late_days'];
            $diff = $new_late_days_available - ($prev_late_days_available + $this->getLateDaysUsedByContext($event['timestamp']));
            $late_days_change = $diff;
            $late_days_remaining = max(0, $late_days_remaining + $diff);

            // Create LateDayInfo from event info
            $event_info = $this->createEventInfo($event, $late_days_remaining, $late_days_change);
            $info = new LateDayInfo($this->core, $this->user, $event_info);
        }

        return $info;
    }

    /**
     * Gets the number of late days remaining as of a certain date
     * Note: This does not apply late day 'updates' retroactively
     * @param \DateTime $context The date to calculate remaining late days for
     * @return int
     */
    public function getLateDaysRemainingByContext(\DateTime $context) {
        $remaining = $this->core->getConfig()->getDefaultStudentLateDays();

        /** @var LateDayInfo $info */
        foreach ($this->late_day_info as $info) {
            if ($info->getLateDayEventTime() > $context) {
                break;
            }
            $remaining = $info->getLateDaysRemaining();
        }
        return $remaining;
    }

    /**
     * Gets the latest version # for the gradeable that is on time
     * (0 if no valid version exists)
     * @param GradedGradeable $gg
     * @return int
     */
    public function getLatestValidVersion(GradedGradeable $gg): int {
        $ldi = $this->getLateDayInfoByGradeable($gg->getGradeable());

        // Get index of this gradeable within chronological ordering
        $g_ids = array_keys($this->late_day_info);
        $found_index = array_search($gg->getGradeableId(), $g_ids);

        // If there was a previous late day event, use those late days remaining
        // else use the default student late days
        $default_late_days = $this->core->getConfig()->getDefaultStudentLateDays();
        $remaining_late_days = $found_index > 0 ? $ldi->getLateDaysRemaining() : $default_late_days;

        return $this->core->getQueries()->getLatestValidGradeableVersion($gg, $gg->getSubmitter()->getId(), $remaining_late_days);
    }

    /**
     * Gets if the user has any late days to use
     * @return bool
     */
    public function hasLateDaysRemaining() {
        return $this->getLateDaysRemaining() > 0;
    }

    /**
     * Gets the LateDaysInfo instance for a gradeable
     * @param Gradeable $gradeable
     * @return LateDayInfo|null
     */
    public function getLateDayInfoByGradeable(Gradeable $gradeable) {
        return $this->late_day_info[$gradeable->getId()] ?? null;
    }

    /**
     * Gets the gradeables with a provided status
     * @param int $status
     * @return string[] Array of gradeable ids
     */
    public function getGradeableIdsByStatus($status) {
        if (!LateDayInfo::isValidStatus($status)) {
            throw new \InvalidArgumentException('Invalid gradeable status');
        }

        return array_keys(array_filter($this->late_day_info, function (LateDayInfo $ldi) use ($status) {
            return $ldi->getStatus() === $status;
        }));
    }
}
