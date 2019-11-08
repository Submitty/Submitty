<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\DateUtils;

class OfficeHoursQueueStudent extends AbstractModel {

    private $user_id = null;
    private $status = 0;
    private $position_in_queue = -1;
    private $name = "";
    private $num_in_queue = 0;
    private $time_in = "time not set";
    private $time_out_iso = "time not set";
    private $time_helped_iso = "time not set";
    private $time_in_iso = "time not set";
    private $removed_by = null;
    private $entry_id = 0;

    /**
    * OfficeHoursQueueStudent constructor.
    *
    * @param Core  $core
    * @param array $details
    */
    public function __construct(Core $core, $entry_id, $user_id, $name, $status, $num_in_queue, $position_in_queue, $time_in, $time_helped, $time_out, $removed_by) {
        parent::__construct($core);
        $this->name = $name;
        $this->entry_id = $entry_id;
        $this->user_id = $user_id;
        $this->status = $status;
        $this->num_in_queue = $num_in_queue;
        $this->position_in_queue = $position_in_queue;
        $this->time_in = date("h:i a", strtotime($time_in));
        $this->time_in_iso = date("c", strtotime($time_in));
        $this->time_out_iso = date("c", strtotime($time_out));
        $this->time_helped_iso = date("c", strtotime($time_helped));
        $this->removed_by = $removed_by;
    }

    public function getName() {
        return $this->name;
    }

    public function getUserId() {
        return $this->user_id;
    }

    public function getPositionInQueue() {
        return $this->position_in_queue;
    }

    public function isInQueue() {
        return $this->status == 0 || $this->status == 1;
    }

    public function getStatus() {
        return $this->status;
    }

    public function getNumInQueue() {
        return $this->num_in_queue;
    }

    public function getTimeIn() {
        return $this->time_in;
    }

    public function getTimeHelpedWithSeconds() {
        return $this->time_helped_iso;
    }


    public function getTimeBeingHelped() {
        $diff = strtotime($this->time_out_iso) - strtotime($this->time_helped_iso);
        $h = $diff / 3600 % 24;
        $m = $diff / 60 % 60;
        $s = $diff % 60;
        return $h . "h " . $m . "m " . $s . "s";
    }

    public function getTimeWaitingInQueue() {
        if ($this->status  == 2) {
            $diff = strtotime($this->time_helped_iso) - strtotime($this->time_in_iso);
        }
        else {
            $diff = strtotime($this->time_out_iso) - strtotime($this->time_in_iso);
        }
        $h = $diff / 3600 % 24;
        $m = $diff / 60 % 60;
        $s = $diff % 60;
        return $h . "h " . $m . "m " . $s . "s";
    }

    public function getRemovedBy() {
         return $this->removed_by;
    }

    public function getEntryId() {
        return $this->entry_id;
    }
}
