<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\DateUtils;


class OfficeHoursQueue extends AbstractModel {

    private $user = null;
    private $in_queue = false;
    private $position_in_queue = -1;
    private $name = "";
    private $num_in_queue = 0;
    /**
     * Notifications constructor.
     *
     * @param Core  $core
     * @param array $details
     */
    public function __construct(Core $core, User $user, $name, $in_queue, $num_in_queue, $position_in_queue) {
        parent::__construct($core);
        $this->name = $name;
        $this->user = $user;
        $this->in_queue = $in_queue;
        $this->num_in_queue = $num_in_queue;
        $this->position_in_queue = $position_in_queue;
    }

    public function getName(){
      return $this->name;
    }

    public function getUser(){
      return $this->user;
    }

    public function getPositionInQueue(){
      return $this->position_in_queue;
    }

    public function isInQueue(){
      return $this->in_queue;
    }

    public function getNumInQueue(){
      return $this->num_in_queue;
    }
}
