<?php
/**
 * Created by PhpStorm.
 * User: mackek4
 * Date: 6/19/2018
 * Time: 8:55 AM
 */

namespace app\models\gradeable;


use app\libraries\Core;
use app\libraries\DateUtils;
use app\libraries\Utils;
use app\models\AbstractModel;

/**
 * Class AutogradingVersion
 * @package app\models\gradeable
 *
 * Data about and results of autograding for one submission version
 * TODO: this should use lazy loading for per-testcase data (found in files on disk)
 *
 * @method int getVersion()
 * @method float getNonHiddenNonExtraCredit()
 * @method float getNonHiddenExtraCredit()
 * @method float getHiddenNonExtraCredit()
 * @method float getHiddenExtraCredit()
 * @method \DateTime getSubmissionTime()
 * @method isAutogradingComplete()
 */
class AutogradingVersion extends AbstractModel {
    /** @var GradedGradeable Reference to the GradedGradeable */
    private $graded_gradeable = null;
    /** @property @var int The submission version for this AutogradingVersion */
    protected $version = 0;
    /** @property @var float The number of "normal" points */
    protected $non_hidden_non_extra_credit = 0;
    /** @property @var float The number of "normal extra credit" points */
    protected $non_hidden_extra_credit = 0;
    /** @property @var float The number of "hidden" points */
    protected $hidden_non_extra_credit = 0;
    /** @property @var float The number of "hidden extra credit" points */
    protected $hidden_extra_credit = 0;
    /** @property @var \Datetime Time the user submitted this version */
    protected $submission_time = null;
    /** @property @var bool If the autograding has complete for this version */
    protected $autograding_complete = false;

    /**
     * AutogradingVersion constructor.
     * @param Core $core
     * @param GradedGradeable $graded_gradeable
     * @param array $details
     * @throws \Exception If \DateTime failed to parse
     */
    public function __construct(Core $core, GradedGradeable $graded_gradeable, array $details) {
        parent::__construct($core);

        if ($graded_gradeable === null) {
            throw new \InvalidArgumentException('Graded gradeable cannot be null');
        }
        $this->graded_gradeable = $graded_gradeable;
        $this->setVersionInternal($details['version']);
        $this->setPointsInternal($details);
        $this->setSubmissionTimeInternal($details['submission_time']);
        $this->setAutogradingCompleteInternal($details['autograding_complete']);
    }

    public function toArray() {
        $details = parent::toArray();

        // Make sure to convert the date into a string
        $details['submission_time'] = DateUtils::dateTimeToString($this->submission_time);

        return $details;
    }

    public function getNonHiddenPoints() {
        return $this->non_hidden_non_extra_credit + $this->non_hidden_extra_credit;
    }

    /**
     * Gets the percent of the possible visible points the submitter earned
     * @param bool $clamp True to clamp the output to 1
     * @return float percentage (0 to 1), or NAN if no visible percent
     */
    public function getNonHiddenPercent($clamp = false) {
        $divisor = $this->graded_gradeable->getGradeable()->getAutogradingConfig()->getTotalNonHiddenNonExtraCredit();
        $dividend = $this->getNonHiddenNonExtraCredit() + $this->getNonHiddenExtraCredit();

        return Utils::safeCalcPercent($dividend, $divisor, $clamp);
    }

    /**
     * Gets the percent of all possible points the submitter earned
     * @param bool $clamp True to clamp the output to 1
     * @return float percentage (0 to 1), or NAN if no points possible
     */
    public function getTotalPercent($clamp = false) {
        $config = $this->graded_gradeable->getGradeable()->getAutogradingConfig();
        $divisor = $config->getTotalNonHiddenNonExtraCredit() + $config->getTotalHiddenNonExtraCredit();
        $dividend = $this->getNonHiddenNonExtraCredit() + $this->getNonHiddenExtraCredit() +
            $this->getHiddenNonExtraCredit() + $this->getHiddenExtraCredit();

        return Utils::safeCalcPercent($dividend, $divisor, $clamp);
    }

    /**
     * Gets the graded gradeable this version data is associated with
     * @return GradedGradeable the graded gradeable this version data is associated with
     */
    public function getGradedGradeable() {
        return $this->graded_gradeable;
    }

    /* Overridden setters with validation */

    /**
     * Sets the version this graded version data is for
     * @param int|string $version
     */
    private function setVersionInternal($version) {
        if ((is_int($version) || ctype_digit($version)) && intval($version) >= 0) {
            $this->version = intval($version);
        } else {
            throw new \InvalidArgumentException('Version number must be a non-negative integer');
        }
    }

    const point_properties = [
        'non_hidden_non_extra_credit',
        'non_hidden_extra_credit',
        'hidden_non_extra_credit',
        'hidden_extra_credit'
    ];

    /**
     * Sets the point properties and asserts that they're numeric
     * @param array $points
     */
    private function setPointsInternal(array $points) {
        foreach (self::point_properties as $property) {
            if (is_numeric($points[$property])) {
                $this->$property = floatval($points[$property]);
            } else {
                throw new \InvalidArgumentException('Graded version point values must be numeric');
            }
        }
    }

    /**
     * Sets the date that the submitter submitted this version
     * @param string|\DateTime $submission_time The date or date string of when the submitter submitted this version
     * @throws \Exception if $submission_time is a string and failed to parse into a \DateTime object
     */
    private function setSubmissionTimeInternal($submission_time) {
        if ($submission_time !== null) {
            $this->submission_time = DateUtils::parseDateTime($submission_time, $this->core->getConfig()->getTimezone());
        } else {
            throw new \InvalidArgumentException('Graded version submission time must not be null');
        }
    }

    /**
     * Sets whether or not autograding has been completed for this version
     * @param bool $complete Is autograding complete for this version
     */
    private function setAutogradingCompleteInternal($complete) {
        $this->autograding_complete = $complete === 'true' || $complete === true;
    }

    /* Intentionally Unimplemented accessor methods (all setters) */

    /** @internal */
    public function setVersion($version) {
        throw new \BadFunctionCallException('Cannot set version number of graded version');
    }

    /** @internal */
    public function setNonHiddenNonExtraCredit($points) {
        throw new \BadFunctionCallException('Cannot set point values of graded version');
    }

    /** @internal */
    public function setNonHiddenExtraCredit($points) {
        throw new \BadFunctionCallException('Cannot set point values of graded version');
    }

    /** @internal */
    public function setHiddenNonExtraCredit($points) {
        throw new \BadFunctionCallException('Cannot set point values of graded version');
    }

    /** @internal */
    public function setHiddenExtraCredit($points) {
        throw new \BadFunctionCallException('Cannot set point values of graded version');
    }

    /** @internal */
    public function setSubmissionTime($submission_time) {
        throw new \BadFunctionCallException('Cannot set submission time of graded version');
    }

    /** @internal */
    public function setAutogradingComplete($complete) {
        throw new \BadFunctionCallException('Cannot set completeness of graded version');
    }
}
