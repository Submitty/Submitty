<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\DateUtils;

/**
 * Class GradeableVersion
 *
 * @method int getVersion()
 * @method float getNonHiddenNonExtraCredit()
 * @method float getNonHiddenExtraCredit()
 * @method float getHiddenNonExtraCredit()
 * @method float getHiddenExtraCredit()
 * @method integer getDaysLate()
 */
class GradeableVersion extends AbstractModel {
    /** @property @var string */
    protected $g_id;
    protected $user_id;
    protected $team_id;
    /** @property @var int */
    protected $version;
    /** @property @var \app\models\GradeableComponent[] */
    protected $components = array();
    protected $been_graded = false;
    /** @property @var float */
    protected $non_hidden_non_extra_credit = 0;
    /** @property @var float */
    protected $non_hidden_extra_credit = 0;
    /** @property @var float */
    protected $hidden_non_extra_credit = 0;
    /** @property @var float */
    protected $hidden_extra_credit = 0;
    /** @property
     * @var \DateTime
     */
    protected $submission_time;
    protected $active = false;
    /** @property @var int */
    protected $days_late = 0;
    /** @property @var int */
    protected $days_early = 0;

    /**
     * GradeableVersion constructor.
     *
     * @param Core $core
     * @param $details
     * @param \DateTime $due_date
     */
    public function __construct(Core $core, $details, \DateTime $due_date) {
        parent::__construct($core);

        $this->g_id = $details['g_id'];
        $this->user_id = $details['user_id'];
        $this->team_id = $details['team_id'];
        $this->version = $details['g_version'];
        // need to put in constructor for components and been_graded after query for getGradeableVersions is updated
        $this->non_hidden_non_extra_credit = $details['autograding_non_hidden_non_extra_credit'];
        $this->non_hidden_extra_credit = $details['autograding_non_hidden_extra_credit'];
        $this->hidden_non_extra_credit = $details['autograding_hidden_non_extra_credit'];
        $this->hidden_extra_credit = $details['autograding_hidden_extra_credit'];
        $this->submission_time = $details['submission_time'];
        // We add a 5 minute buffer for submissions before they're considered "late"
        $extended_due_date = clone $due_date;
        $this->days_late = DateUtils::calculateDayDiff($extended_due_date->add(new \DateInterval("PT5M")), $this->submission_time, $this->core->getConfig()->getTimezone());
        $this->days_early = DateUtils::calculateDayDiff($this->submission_time, $extended_due_date, $this->core->getConfig()->getTimezone());
        if ($this->days_late < 0) {
            $this->days_late = 0;
        }
        else if ($this->days_early < 0) {
            $this->days_early = 0;
        }
        
        $this->active = isset($details['active_version']) && $details['active_version'] === true;
    }

    public function getNonHiddenTotal() {
        return $this->non_hidden_non_extra_credit + $this->non_hidden_extra_credit;
    }

    public function getHiddenTotal() {
        return $this->hidden_non_extra_credit + $this->hidden_extra_credit;
    }

    public function isActive() {
        return $this->active;
    }

    public function getSubmissionTime() {
        return $this->submission_time->format("m/d/Y h:i:s A");
    }
}
