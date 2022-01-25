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
    /** @prop @var LateDayInfo[] The late day info of each gradeable, indexed by gradeable id */
    protected $late_day_info = [];
    /** @prop @var array All entries for the user in the `late_days` table */
    protected $late_days_updates = [];

    /**
     * LateDays constructor.
     * NOTE: use LateDays::fromUser if you want to use default gradeable filtering behavior
     * @param Core $core
     * @param User $user
     * @param GradedGradeable[] $graded_gradeables An array of only GradedGradeables
     */
    public function __construct(Core $core, User $user, array $graded_gradeables, $late_day_updates = null) {
        parent::__construct($core);
        $this->user = $user;

        // Filter out non-electronic gradeables
        $graded_gradeables = array_filter($graded_gradeables, function (GradedGradeable $gg) {
            return $gg->getGradeable()->getType() === GradeableType::ELECTRONIC_FILE;
        });

        // Create key value pairs
        $ggs = [];
        foreach ($graded_gradeables as $gg) {
            $ggs[$gg->getGradeableId()] = $gg;
        }

        // Get the late day information from the database
        $this->core->getQueries()->generateLateDayCacheForUser($user->getId());
        $late_day_cache = $this->core->getQueries()->getLateDayCacheForUser($user->getId());

        // Construct late days info for each gradeable
        foreach ($late_day_cache as $id => $ldc) {
            $ldc['graded_gradeable'] = $ggs[$id] ?? null;
            $info = new LateDayInfo(
                $core,
                $user,
                $ldc
            );
            $this->late_day_info[$id] = $info;
        }
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
            if ($info->getGradedGradeable()->getGradeable()->getSubmissionDueDate() > $context) {
                break;
            }
            $total += $info->getLateDaysCharged();
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
