<?php

namespace app\models;

use app\libraries\DateUtils;

class GradeableVersion {
    private $g_id;
    private $user_id;
    private $version;
    private $non_hidden_non_extra_credit = 0;
    private $non_hidden_extra_credit = 0;
    private $hidden_non_extra_credit = 0;
    private $hidden_extra_credit = 0;
    private $submission_time;
    private $active = false;

    /**
     * GradeableVersion constructor.
     * @param $details
     * @param \DateTime $due_date
     * @param \DateTimeZone $timezone
     */
    public function __construct($details, $due_date, $timezone) {
        $this->g_id = $details['g_id'];
        $this->user_id = $details['user_id'];
        $this->version = $details['g_version'];
        $this->non_hidden_non_extra_credit = $details['autograding_non_hidden_non_extra_credit'];
        $this->non_hidden_extra_credit = $details['autograding_non_hidden_extra_credit'];
        $this->hidden_non_extra_credit = $details['autograding_hidden_non_extra_credit'];
        $this->hidden_extra_credit = $details['autograding_hidden_extra_credit'];
        $this->submission_time = new \DateTime($details['submission_time'], $timezone);
        // We add a 5 minute buffer for submissions before they're considered "late"
        $this->days_late = DateUtils::calculateDayDiff($due_date->add(new \DateInterval("PT5M")), $this->submission_time);
        if ($this->days_late < 0) {
            $this->days_late = 0;
        }
        $this->active = $details['active_version'] === true;
    }

    public function getVersion() {
        return $this->version;
    }

    public function getNonHiddenTotal() {
        return $this->non_hidden_non_extra_credit + $this->non_hidden_extra_credit;
    }

    public function getNonHiddenNonExtraCreditTotal() {
        return $this->non_hidden_non_extra_credit;
    }

    public function isActive() {
        return $this->active;
    }

    public function getDaysLate() {
        return $this->days_late;
    }

    public function getSubmissionTime() {
        return $this->submission_time->format("m/d/Y h:i:s A");
    }
}