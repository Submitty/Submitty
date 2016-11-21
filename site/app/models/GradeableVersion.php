<?php

namespace app\models;

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

    public function __construct($details) {
        $this->g_id = $details['g_id'];
        $this->user_id = $details['user_id'];
        $this->version = $details['g_version'];
        $this->non_hidden_non_extra_credit = $details['autograding_non_hidden_non_extra_credit'];
        $this->non_hidden_extra_credit = $details['autograding_non_hidden_extra_credit'];
        $this->hidden_non_extra_credit = $details['autograding_hidden_non_extra_credit'];
        $this->hidden_extra_credit = $details['autograding_extra_credit'];
        $this->submission_time = $details['submission_time'];
        $this->active =  $details['active'];
    }

    public function getVersion() {
        return $this->version;
    }

    public function getTotalNonHidden() {
        return $this->non_hidden_non_extra_credit + $this->non_hidden_extra_credit;
    }

    public function getNonHiddenNonExtraCredit() {
        return $this->non_hidden_non_extra_credit;
    }
}