<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\DateUtils;
use app\models\OfficeHoursQueueStudent;


class OfficeHoursQueueInstructor extends AbstractModel {

    private $entries = array();
    /**
     * Notifications constructor.
     *
     * @param Core  $core
     * @param array $details
     */
    public function __construct(Core $core, User $user, OfficeHoursQueueStudent ...$entries) {
        parent::__construct($core);
        $this->entries = $entries;
    }

    public function getEntries(){
      return $entries;
    }
}
