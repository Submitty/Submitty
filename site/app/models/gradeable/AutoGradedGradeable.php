<?php
/**
 * Created by PhpStorm.
 * User: mackek4
 * Date: 6/25/2018
 * Time: 2:30 PM
 */

namespace app\models\gradeable;


use app\libraries\Core;
use app\models\AbstractModel;

/**
 * Class AutoGradedGradeable
 * @package app\models\gradeable
 *
 * @method int getActiveVersion()
 * @method AutogradingVersion[] getAutogradingVersions()
 */
class AutoGradedGradeable extends AbstractModel {
    /** @property @var GradedGradeable A reference to the graded gradeable this auto grade belongs to */
    private $graded_gradeable = null;
    /** @property @var int The active submission version for electronic gradeables */
    protected $active_version = 0;
    /** @property @var AutogradingVersion[] The graded versions for electronic gradeables */
    protected $autograding_versions = array();

    /**
     * AutoGradedGradeable constructor.
     * @param Core $core
     * @param GradedGradeable $graded_gradeable
     * @param array $details
     * @throws \InvalidArgumentException if any of the details are invalid or the graded gradeable is null
     */
    public function __construct(Core $core, GradedGradeable $graded_gradeable, array $details) {
        parent::__construct($core);

        $this->setActiveVersion($details['active_version']);
        if($graded_gradeable === null) {
            throw new \InvalidArgumentException('Graded gradeable cannot be null');
        }
        $this->graded_gradeable = $graded_gradeable;
        $this->modified = false;
    }

    public function toArray() {
        $details = parent::toArray();

        // Uncomment this block if we want to serialize the scores
//        $visible_percent = $this->getVisiblePercent();
//        $visible_percent = is_nan($visible_percent) ? 0 : $visible_percent;
//        $details['visible_score'] = $visible_percent;
//
//        $total_score = $this->getTotalPercent();
//        $total_score = is_nan($total_score) ? 0 : $total_score;
//        $details['total_score'] = $total_score;

        return $details;
    }

    /**
     * Gets the AutogradingVersion instance for the active version
     * @return AutogradingVersion
     */
    public function getActiveVersionInstance() {
        return $this->autograding_versions[$this->active_version];
    }

    /**
     * Sets the active submission version for this electronic gradeable
     * @param int $version
     */
    public function setActiveVersion($version) {
        if ((is_int($version) || ctype_digit($version)) && intval($version) >= 0) {
            $this->active_version = intval($version);
        } else {
            throw new \InvalidArgumentException('Active version must be a non-negative integer');
        }
        $this->modified = true;
    }

    /**
     * Sets the array of autograding versions for this gradeable data
     * @param AutogradingVersion[] $autograding_versions
     */
    public function setAutogradingVersions(array $autograding_versions) {
        foreach ($autograding_versions as $autograding_version) {
            if (!($autograding_version instanceof AutogradingVersion)) {
                throw new \InvalidArgumentException('Autograding version array contained invalid type');
            }
        }
        $this->autograding_versions = [];
        foreach ($autograding_versions as $autograding_version) {
            $this->autograding_versions[$autograding_version->getVersion()] = $autograding_version;
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
        if($instance === null) {
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
        if($instance === null) {
            return NAN;
        }
        return $instance->getTotalPercent($clamp);
    }
}