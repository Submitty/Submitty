<?php

namespace app\models\gradeable;

use app\libraries\Core;
use app\libraries\GradingQueue;
use app\models\AbstractModel;

/**
 * Class AutoGradedGradeable
 * @package app\models\gradeable
 *
 * @method int getActiveVersion()
 * @method AutoGradedVersion[] getAutoGradedVersions()
 */
class AutoGradedGradeable extends AbstractModel {
    /** @prop @var GradedGradeable A reference to the graded gradeable this auto grade belongs to */
    private $graded_gradeable = null;
    /** @prop @var int The active submission version for electronic gradeables */
    protected $active_version = 0;
    /** @prop @var AutoGradedVersion[] The graded versions for electronic gradeables */
    protected $auto_graded_versions = [];

    /**
     * AutoGradedGradeable constructor.
     * @param Core $core
     * @param GradedGradeable $graded_gradeable
     * @param array $details
     * @throws \InvalidArgumentException if any of the details are invalid or the graded gradeable is null
     */
    public function __construct(Core $core, GradedGradeable $graded_gradeable, array $details) {
        parent::__construct($core);

        if ($graded_gradeable === null) {
            throw new \InvalidArgumentException('Graded gradeable cannot be null');
        }
        $this->setActiveVersion($details['active_version'] ?? 0);
        $this->graded_gradeable = $graded_gradeable;
        $this->modified = false;
    }

    public function toArray() {
        return parent::toArray();
    }

    /**
     * Gets the graded gradeable instance this auto graded gradeable belongs to
     * @return GradedGradeable
     */
    public function getGradedGradeable() {
        return $this->graded_gradeable;
    }

    /**
     * Gets the AutoGradedVersion instance for the specified version, or null
     * if no such version exists
     * @param int $version Version number
     * @return AutoGradedVersion|null
     */
    public function getAutoGradedVersionInstance(int $version) {
        if (array_key_exists($version, $this->auto_graded_versions)) {
            return $this->auto_graded_versions[$version];
        }
        return null;
    }

    /**
     * Gets the AutoGradedVersion instance for the active version
     * @return AutoGradedVersion
     */
    public function getActiveVersionInstance() {
        return $this->auto_graded_versions[$this->active_version] ?? null;
    }

    /**
     * Sets the active submission version for this electronic gradeable
     * @param int $version
     */
    public function setActiveVersion($version) {
        if ((is_int($version) || ctype_digit($version)) && intval($version) >= 0) {
            $this->active_version = intval($version);
        }
        else {
            throw new \InvalidArgumentException('Active version must be a non-negative integer');
        }
        $this->modified = true;
    }

    /**
     * Sets the array of autograding versions for this gradeable data
     * @param AutoGradedVersion[] $auto_graded_versions
     */
    public function setAutoGradedVersions(array $auto_graded_versions) {
        foreach ($auto_graded_versions as $auto_graded_version) {
            if (!($auto_graded_version instanceof AutoGradedVersion)) {
                throw new \InvalidArgumentException('Autograding version array contained invalid type');
            }
        }
        $this->auto_graded_versions = [];
        foreach ($auto_graded_versions as $auto_graded_version) {
            $this->auto_graded_versions[$auto_graded_version->getVersion()] = $auto_graded_version;
        }
    }

    /* Percentage score access methods */

    /**
     * Gets the percent of the possible visible points the submitter earned
     *  for the active version
     * @param bool $clamp True to clamp the output to 1
     * @return float percentage (0 to 1), or NAN if no visible percent or no active version
     */
    public function getNonHiddenPercent($clamp = false) {
        $instance = $this->getActiveVersionInstance();
        if ($instance === null) {
            return NAN;
        }
        return $instance->getNonHiddenPercent($clamp);
    }

    /**
     * Gets the percent of all possible points the submitter earned
     *  for the active version
     * @param bool $clamp True to clamp the output to 1
     * @return float percentage (0 to 1), or NAN if no points possible or no active version
     */
    public function getTotalPercent($clamp = false) {
        $instance = $this->getActiveVersionInstance();
        if ($instance === null) {
            return NAN;
        }
        return $instance->getTotalPercent($clamp);
    }

    /**
     * Gets the points the submitter earned for the active version
     * @return int
     */
    public function getTotalPoints() {
        $instance = $this->getActiveVersionInstance();
        if ($instance === null) {
            return NAN;
        }
        return $instance->getTotalPoints();
    }

    /**
     * Gets the highest submitted version number
     * @return int
     */
    public function getHighestVersion() {
        $highest_version = 0;
        foreach ($this->auto_graded_versions as $auto_graded_version) {
            $highest_version = max($highest_version, $auto_graded_version->getVersion());
        }
        return $highest_version;
    }

    /**
     * Gets if the submitter has a version selected for grading
     * @return bool
     */
    public function hasActiveVersion() {
        return $this->active_version > 0;
    }

    /**
     * Gets if the autograding is finished for the active version (if one)
     * @return bool
     */
    public function isAutoGradingComplete() {
        return $this->hasActiveVersion() && $this->getActiveVersionInstance()->isAutogradingComplete();
    }

    /**
     * Gets if there are any submissions for this submitter
     * @return bool
     */
    public function hasSubmission() {
        return count($this->auto_graded_versions) > 0;
    }

    /* Queue status access methods */

    /**
     * Gets if the active version is in the queue to be graded
     * @return bool
     */
    public function isQueued() {
        $instance = $this->getActiveVersionInstance();
        if ($instance === null) {
            return false;
        }
        return $instance->isQueued();
    }

    /**
     * Gets if the active version is being graded
     * @return bool
     */
    public function isGrading() {
        $instance = $this->getActiveVersionInstance();
        if ($instance === null) {
            return false;
        }
        return $instance->isGrading();
    }

    /**
     * Gets the position of the active version in the queue
     * @return int GradingQueue::GRADING if being graded, GradingQueue::NOT_QUEUED if not in queue,
     *              otherwise the queue count
     */
    public function getQueuePosition() {
        $instance = $this->getActiveVersionInstance();
        if ($instance === null) {
            return GradingQueue::NOT_QUEUED;
        }
        return $instance->getQueuePosition();
    }
}
