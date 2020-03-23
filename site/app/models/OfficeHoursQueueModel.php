<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\DateUtils;
use app\models\OfficeHoursQueueModel;

class OfficeHoursQueueModel extends AbstractModel {


    /*
    current_state values
        ('waiting'):Waiting
        ('being_helped'):Being helped
        ('done'):Done/Fully out of the queue
    removal_type values
        (null):Still in queue
        ('self'):Removed yourself
        ('helped'):Mentor/TA helped you
        ('removed'):Mentor/TA removed you
        ('emptied'):Kicked out because queue emptied
        ('self_helped'):You helped you
    */

    private $code_to_index = array();//an array maps queue codes to their index (this is used to give each queue a color)
    private $current_queue;
    private $full_history;
    private $colors = array('#c98ee4','#9fcc55','#ea79a1','#4ed78e','#ef7568','#38b3eb','#e09965','#8499e3','#83cc88','#d9ab39','#4ddcc0','#b9c673','#658bfb','#76cc6c','#dc8b3d','#c9bf5d','#5499f0','#9a89f0','#e57fcf','#c0c246');

    /**
    * OfficeHoursQueueModel constructor.
    *
    * @param Core  $core
    */
    public function __construct(Core $core, $full_history = false) {
        parent::__construct($core);
        $index = 0;
        foreach ($this->core->getQueries()->getAllQueues() as $queue) {
            $this->code_to_index[$queue['code']] = $index;
            $index += 1;
        }

        $this->current_queue = $this->core->getQueries()->getCurrentQueue();
        $this->full_history = $full_history === 'true';
    }

    public function getIndexFromCode($code) {
        if (array_key_exists($code, $this->code_to_index)) {
            return $this->code_to_index[$code];
        }
        else {
            return "";
        }
    }

    public function isGrader() {
        return $this->core->getUser()->accessGrading();
    }

    public function getUserId() {
        return $this->core->getUser()->getId();
    }

    public function isAnyQueueOpen() {
        return $this->core->getQueries()->isAnyQueueOpen();
    }

    public function getName() {
        $name = $this->core->getQueries()->getLastUsedQueueName();
        if (is_null($name)) {
            return $this->core->getUser()->getDisplayedFirstName() . " " . $this->core->getUser()->getDisplayedLastName();
        }
        return $name;
    }

    public function getContactInfo() {
        $contact_info = $this->core->getQueries()->getLastUsedContactInfo();
        if (is_null($contact_info)) {
            return "";
        }
        return $contact_info;
    }

    public function getCurrentQueue() {
        return $this->current_queue;
    }

    public function getPastQueue() {
        return $this->core->getQueries()->getPastQueue();
    }

    public function getAllQueues() {
        return $this->core->getQueries()->getAllQueues();
    }

    public function timeToHM($time) {
        return date_format(date_create($time), "g:iA");
    }

    public function timeToISO($time) {
        return date_format(date_create($time), "c");
    }

    public function getTimeBeingHelped($time_out, $time_helped) {
        $diff = strtotime($time_out) - strtotime($time_helped);
        $h = $diff / 3600 % 24;
        $m = $diff / 60 % 60;
        $s = $diff % 60;
        return $h . "h " . $m . "m " . $s . "s";
    }

    public function getTimeWaitingInQueue($time_out, $time_helped, $time_in, $removal_type) {
        if (in_array($removal_type, array('helped','self_helped'))) {
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

    //gets the number of people ahead of you in the queue.
    //If no queue_code is passed it assumes you want the total number in all queues
    public function getQueueNumberAheadOfYou($queue_code = null) {
        return $this->core->getQueries()->getQueueNumberAheadOfYou($queue_code);
    }

    public function firstTimeInQueue($id, $queue_code) {
        return $this->core->getQueries()->firstTimeInQueue($id, $queue_code);
    }

    public function inQueue() {
        return $this->core->getQueries()->alreadyInAQueue();
    }

    public function getCurrentQueueCode() {
        return $this->core->getQueries()->getCurrentQueueState()['queue_code'];
    }

    public function getCurrentQueueStatus() {
        return $this->core->getQueries()->getCurrentQueueState()['current_state'];
    }

    public function cleanForId($str) {
        return strtoupper($str);
    }

    public function getLastQueueUpdate() {
        return $this->core->getQueries()->getLastQueueUpdate();
    }

    public function getFullHistory() {
        return $this->full_history;
    }

    public function getColors() {
        return $this->colors;
    }

    public function getColor($index) {
        return $this->colors[$index];
    }

    public function removeUnderScores($value) {
        return preg_replace('/_/', ' ', $value);
    }

    public function isContactInfoEnabled() {
        return $this->core->getConfig()->getQueueContactInfo();
    }

    public function getQueueMessage() {
        return $this->core->getConfig()->getQueueMessage();
    }
}
