<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\DateUtils;
use app\models\OfficeHoursQueueStudent;

class OfficeHoursQueueInstructor extends AbstractModel {

    private $entries = array();
    private $entries_helped = array();
    private $queue_open = false;
    private $code = '';


    //Status codes
    //Status codes are used in the database to keep track of the current state
    //a student is in within the queue
    const STATUS_CODE_IN_QUEUE = 0;//student is waiting in the queue
    const STATUS_CODE_BEING_HELPED = 1;//student is currently being helped
    const STATUS_CODE_SUCCESSFULLY_HELPED = 2;//student was successfully helped and is no longer in the queue
    const STATUS_CODE_REMOVED_BY_INSTRUCTOR = 3;//student was removed by the instructor
    const STATUS_CODE_REMOVED_THEMSELVES = 4;//student removed themselves from the queue
    const STATUS_CODE_BULK_REMOVED = 5;//student was removed after the empty queue button was pressed

    /**
    * OfficeHoursQueueInstructor constructor.
    *
    * @param Core  $core
    * @param array $details
    */
    public function __construct(Core $core, array $entries, array $entries_helped, $queue_open, $code) {
        parent::__construct($core);
        $this->entries = $entries;
        $this->entries_helped = $entries_helped;
        $this->queue_open = $queue_open;
        $this->code = $code;
    }

    public function getEntries() {
        return $this->entries;
    }

    public function getEntriesHelped() {
        return $this->entries_helped;
    }

    public function isQueueOpen() {
        return $this->queue_open;
    }

    public function getCode() {
        return $this->code;
    }
}
