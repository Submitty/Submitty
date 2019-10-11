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
    /**
     * Notifications constructor.
     *
     * @param Core  $core
     * @param array $details
     */
    public function __construct(Core $core, $user_id, $name, $status, $num_in_queue, $position_in_queue, $timestamp) {
        parent::__construct($core);
        $this->name = $name;
        $this->user_id = $user_id;
        $this->status = $status;
        $this->num_in_queue = $num_in_queue;
        $this->position_in_queue = $position_in_queue;
        $this->time_in = date("H:i", strtotime($timestamp));
    }

    public function getName(){
      return $this->name;
    }

    public function getUserId(){
      return $this->user_id;
    }

    public function getPositionInQueue(){
      return $this->position_in_queue;
    }

    public function isInQueue(){
      return $this->status == 0 or $this->status == 1;
    }

    public function getStatus(){
      return $this->status;
    }

    public function getNumInQueue(){
      return $this->num_in_queue;
    }

    public function getTimeIn(){
      return $this->time_in;
    }
}
