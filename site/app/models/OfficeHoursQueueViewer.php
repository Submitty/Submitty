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


    /**
    * OfficeHoursQueueViewer constructor.
    *
    * @param Core  $core
    * @param array $details
    */
    public function __construct(Core $core, array $queue_members, array $queue_states, array $queue_codes, $is_in_queue) {
        parent::__construct($core);
        // $this->can_grade = $this->core->getUser()->accessGrading();
        // $this->queue_members = $queue_members;
        // $this->queue_states = $queue_states;
        // $this->queue_codes = $queue_codes;
        // $this->is_in_queue = $is_in_queue;
    }


    public function isAnyQueueOpen(){
        return $this->core->getQueries()->isAnyQueueOpen();
    }

    public function getName(){
        //TODO return the last name from the database as well as default to the user's real name
        return "default name";
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
