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

    public function getEntries(){
        return $this->entries;
    }

    public function getEntriesHelped(){
        return $this->entries_helped;
    }

    public function isQueueOpen(){
        return $this->queue_open;
    }

    public function getCode(){
        return $this->code;
    }
}
