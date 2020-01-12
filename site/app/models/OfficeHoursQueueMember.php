<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\DateUtils;

class OfficeHoursQueueMember extends AbstractModel {

    private $entry_id;
    private $status_code;
    private $queue_code;
    private $user_id;
    private $name;
    private $time_in;
    private $time_help_start;
    private $time_out;
    private $added_by;
    private $help_started_by;
    private $removed_by;

    /**
    * OfficeHoursQueueStudent constructor.
    *
    * @param Core  $core
    * @param array $details
    */
    public function __construct(Core $core, $entry_id, $status_code, $queue_code, $user_id, $name, $time_in, $time_help_start, $time_out, $added_by, $help_started_by, $removed_by) {
        parent::__construct($core);

        $this->entry_id = $entry_id;
        $this->status_code = $status_code;
        $this->queue_code = $queue_code;
        $this->user_id = $user_id;
        $this->name = $name;
        $this->time_in = $time_in;
        $this->time_help_start = $time_help_start;
        $this->time_out = $time_out;
        $this->added_by = $added_by;
        $this->help_started_by = $help_started_by;
        $this->removed_by = $removed_by;

        // $this->name = $name;
        // $this->entry_id = $entry_id;
        // $this->user_id = $user_id;
        // $this->status = $status;
        // $this->num_in_queue = $num_in_queue;
        // $this->position_in_queue = $position_in_queue;
        // $this->time_in = date("h:i a", strtotime($time_in));
        // $this->time_in_iso = date("c", strtotime($time_in));
        // $this->time_out_iso = date("c", strtotime($time_out));
        // $this->time_helped_iso = date("c", strtotime($time_helped));
        // $this->removed_by = $removed_by;
    }

    // public function getName() {
    //     return $this->name;
    // }
    //
    // public function getUserId() {
    //     return $this->user_id;
    // }
    //
    // public function getPositionInQueue() {
    //     return $this->position_in_queue;
    // }
    //
    // public function isInQueue() {
    //     return $this->status == 0 || $this->status == 1;
    // }
    //
    // public function getStatus() {
    //     return $this->status;
    // }
    //
    // public function getNumInQueue() {
    //     return $this->num_in_queue;
    // }
    //
    // public function getTimeIn() {
    //     return $this->time_in;
    // }
    //
    // public function getTimeHelpedWithSeconds() {
    //     return $this->time_helped_iso;
    // }
    //
    //
    // public function getTimeBeingHelped() {
    //     $diff = strtotime($this->time_out_iso) - strtotime($this->time_helped_iso);
    //     $h = $diff / 3600 % 24;
    //     $m = $diff / 60 % 60;
    //     $s = $diff % 60;
    //     return $h . "h " . $m . "m " . $s . "s";
    // }
    //
    // public function getTimeWaitingInQueue() {
    //     if ($this->status  == 2) {
    //         $diff = strtotime($this->time_helped_iso) - strtotime($this->time_in_iso);
    //     }
    //     else {
    //         $diff = strtotime($this->time_out_iso) - strtotime($this->time_in_iso);
    //     }
    //     $h = $diff / 3600 % 24;
    //     $m = $diff / 60 % 60;
    //     $s = $diff % 60;
    //     return $h . "h " . $m . "m " . $s . "s";
    // }
    //
    // public function getRemovedBy() {
    //      return $this->removed_by;
    // }
    //
    // public function getEntryId() {
    //     return $this->entry_id;
    // }
}
