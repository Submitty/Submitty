<?php

namespace app\models\gradeable;


use app\libraries\Core;
use app\models\AbstractModel;
use app\models\User;

/**
 * Class LateDays
 * @package app\models\gradeable
 *
 * Late day calculation model per user
 */
class LateDays extends AbstractModel {

    /** @var User|null The user to whom this data belongs */
    private $user = null;
    /** @property @var LateDayInfo[] The late day info of each gradeable, indexed by gradeable id */
    protected $late_day_info = [];
    /** @property @var array All entries for the user in the `late_days` table */
    protected $late_days_updates = [];

    const STATUS_NO_SUBMISSION = 0;
    const STATUS_GOOD = 1;
    const STATUS_LATE = 2;
    const STATUS_BAD = 3;

    public static function isValidStatus($status) {
        return in_array($status, [self::STATUS_GOOD, self::STATUS_LATE, self::STATUS_BAD]);
    }

    /**
     * LateDays constructor.
     * @param Core $core
     * @param User $user
     * @param GradedGradeable[] $graded_gradeables An array of only electronic GradedGradeables
     */
    public function __construct(Core $core, User $user, array $graded_gradeables) {
        parent::__construct($core);
        $this->user = $user;

        // Sort by due date
        usort($graded_gradeables, function (GradedGradeable $gg1, GradedGradeable $gg2) {
            return $gg1->getGradeable()->getSubmissionDueDate() - $gg2->getGradeable()->getSubmissionDueDate();
        });

        // Get the late days allowed data
        $this->late_days_updates = $late_days_updates = $this->core->getQueries()->getLateDayUpdates($user->getId());

        $charged_late_days = 0;
        foreach ($graded_gradeables as $graded_gradeable) {
            $info = new LateDayInfo($core, $user, $graded_gradeable, $charged_late_days,
                $this->getLateDaysAvailableByContext($graded_gradeable->getGradeable()->getSubmissionDueDate()));
            $charged_late_days += $info->getLateDaysCharged();
            $this->late_day_info[$graded_gradeable->getGradeableId()] = $info;
        }
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
     */
    public function getLateDaysUsed() {
        $total = 0;
        /** @var LateDayInfo $info */
        foreach ($this->late_day_info as $info) {
            $total += $info->getDaysLate();
        }
        return $total;
    }

    public function getLateDaysRemaining() {
        // Use 'now' because it is possible that there are changes that occur in the future
        return $this->getLateDaysRemainingByContext(new \DateTime());
    }

    public function getLateDaysRemainingByContext(\DateTime $context) {
        // Get the most recent update
        $context_update = $this->getLateDaysUpdateByContext($context);

        // TODO: we have to step through each array, constantly updating remaining with available and charged
        // TODO: making sure it doesn't go below zero....

        // Sum all late days charged since then, clamping to 0 each time
        $remaining = 0;
        /** @var LateDayInfo $info */
        foreach ($this->late_day_info as $info) {
            if ($info->getGradedGradeable()->getGradeable()->getSubmissionDueDate() < $context_update['since_timestamp']) {
                continue;
            }
            $charged += $info->getLateDaysCharged();
        }
        return $available - $charged;
    }

    /**
     * Gets the number of late days the user has available to them now
     *  Note: This value will not be negative
     */
    public function getLateDaysAvailable() {
        // Use 'now' because it is possible that there are changes that occur in the future
        return $this->getLateDaysAvailableByContext(new \DateTime());
    }

    public function getLateDaysAvailableByContext(\DateTime $context) {
        return $this->getLateDaysUpdateByContext($context)['allowed_late_days'];
    }

    /**
     * Gets the number of late days available to a student at a given time context
     *  Note: this is significant if a student has been penalized late days
     * @param \DateTime $context
     * @return array
     */
    public function getLateDaysUpdateByContext(\DateTime $context) {
        if (count($this->late_days_updates) === 0) {
            return [
                'user_id' => $this->user->getId(),
                'allowed_late_days' => $this->core->getConfig()->getDefaultStudentLateDays(),
                'since_timestamp' => new DateTime('1899-12-31') // seems early enough
            ];
        }

        $i = 0;
        // While the submission due date is later than the current late day update, try the next one
        while (isset($late_days_updates[$i + 1])) {
            if ($context <= $this->late_days_updates[$i + 1]['since_timestamp']) {
                break;
            }
            $this++;
        }
        return $this->late_days_updates[$i];
    }

    /**
     * Gets if the user has any late days to use
     * @return bool
     */
    public function hasLateDaysRemaining() {
        return $this->getLateDaysAvailable() > 0;
    }

    /**
     * Gets the LateDaysInfo instance for a gradeable
     * @param Gradeable $gradeable
     * @return LateDayInfo
     */
    public function getLateDayInfo(Gradeable $gradeable) {
        return $this->late_day_info[$gradeable->getId()];
    }

    /**
     * Gets the gradeables with a provided status
     * @param $status
     * @return array
     */
    public function getGradeablesByStatus($status) {
        if (!self::isValidStatus($status)) {
            throw new \InvalidArgumentException('Invalid gradeable status');
        }

        return array_keys(array_filter($this->late_day_info, function (LateDayInfo $ldi) use ($status) {
            return $ldi->getStatus() === $status;
        }));
    }
}