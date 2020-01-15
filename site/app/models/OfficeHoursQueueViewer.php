<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\DateUtils;
use app\models\OfficeHoursQueueViewer;

class OfficeHoursQueueViewer extends AbstractModel {

    // private $can_grade;
    // private $queue_members = array();//Array of all OfficeHoursQueueMembers in the queue
    // private $queue_states = array();//Map from queue ids to if they are open or not
    // private $queue_codes = array();//Map from queue ids to the code for that queue
    // private $is_in_queue;

    private $code_to_index = array();//an array maps queue codes to their index (this is used to give each queue a color)


    /**
    * OfficeHoursQueueViewer constructor.
    *
    * @param Core  $core
    */
    public function __construct(Core $core) {
        parent::__construct($core);

        $index = 0;
        foreach($this->core->getQueries()->getAllQueues() as $queue){
            $code_to_index[$queue['code']] = $index;
            $index += 1;
        }

    }

    public function getIndexFromCode($code){
        return $code_to_index[$code];
    }

    public function isGrader(){
        return $this->core->getUser()->accessGrading();
    }


    public function isAnyQueueOpen(){
        return $this->core->getQueries()->isAnyQueueOpen();
    }

    public function getName(){
        //TODO return the last name from the database as well as default to the user's real name
        return "default name";
    }

    public function getCurrentQueue(){
        return $this->core->getQueries()->getCurrentQueue();
    }

    public function getPastQueue(){
        return $this->core->getQueries()->getPastQueue();
    }

    public function getStateInQueue($status){
        return $status[1];
    }

    public function getLeaveReason($status){
        return $status[2];
    }

    public function getAllQueues(){
        return $this->core->getQueries()->getAllQueues();
    }

    public function timeToHM($time){
        return date_format(date_create($time),"g:iA");
    }

    public function timeToISO($time){
        return date_format(date_create($time),"c");
    }

    public function getTimeBeingHelped($time_out,$time_helped) {
        $diff = strtotime($time_out) - strtotime($time_helped);
        $h = $diff / 3600 % 24;
        $m = $diff / 60 % 60;
        $s = $diff % 60;
        return $h . "h " . $m . "m " . $s . "s";
    }

    public function getTimeWaitingInQueue($time_out,$time_helped,$time_in) {
        if ($this->status  == 2) {
            $diff = strtotime($time_helped) - strtotime($time_in);
        }
        else {
            $diff = strtotime($time_out) - strtotime($time_in);
        }
        $h = $diff / 3600 % 24;
        $m = $diff / 60 % 60;
        $s = $diff % 60;
        return $h . "h " . $m . "m " . $s . "s";
    }

    // public function getViewerType() {
    //     return $this->viewer_type;
    // }
    //
    // public function getMembers() {
    //     return $this->queue_members;
    // }
    //
    // public function getStates() {
    //     return $this->queue_states;
    // }
    //
    // public function getCodes() {
    //     return $this->queue_codes;
    // }
    //
    // public function isAnyQueueOpen(){
    //     foreach($this->queue_states as $state){
    //         if($state == true){
    //             return true;
    //         }
    //     }
    //     return false;
    // }
    //
    // public function isInQueue(){
    //     return $is_in_queue;
    // }
    //
    // public function numberInQueue(){
    //     return 100;
    // }
}
