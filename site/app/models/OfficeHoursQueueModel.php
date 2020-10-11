<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\DateUtils;
use DateTime;

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

    private $code_to_index = [];//an array maps queue codes to their index (this is used to give each queue a color)
    private $current_queue;
    private $full_history;
    private $current_queue_state;
/*<<<<<<< HEAD */
    private $colors = ['#c3a2d2','#99b270','#cd98aa','#6bb88f','#c8938d','#6b9fb8','#c39e83','#98a3cd','#8ac78e','#b39b61','#6eb9aa','#b4be79','#94a2cc','#80be79','#b48b64','#b9b26e','#83a0c3','#ada5d4','#e57fcf','#c0c246'];
/*=======
    private $colors = ['#c98ee4','#9fcc55','#ea79a1','#4ed78e','#ef7568','#38b3eb','#e09965','#8499e3','#83cc88','#d9ab39','#4ddcc0','#b9c673','#658bfb','#76cc6c','#dc8b3d','#c9bf5d','#5499f0','#9a89f0','#e57fcf','#c0c246'];*/
    private $days = [
        'Sunday',
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday'
    ];
    private $niceNames = [
      'dow' => 'Day',
      'queue_interactions' => 'Total Interactions',
      'number_distinct_students' => 'Distinct Students',
      'avg_help_time' => 'Avg Help Time',
      'min_help_time' => 'Min Help Time',
      'max_help_time' => 'Max Help Time',
      'avg_wait_time' => 'Avg Wait Time',
      'min_wait_time' => 'Min Wait Time',
      'max_wait_time' => 'Max Wait Time',
      'help_count' => 'Helps',
      'not_helped_count' => 'Unhelped Students',
      'queue_code' => 'Queue',
      'weeknum' => 'Week',
      'number_names_used' => 'Unique Names',
    ];

//>>>>>>> master

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

        if (!$this->isGrader()) {
            $this->current_queue_state = $this->core->getQueries()->getCurrentQueueState();
        }
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
        $date_time = new \DateTime($time);
        $date_time->setTimezone($this->core->getConfig()->getTimezone());
        return DateUtils::convertTimeStamp($this->core->getUser(), $date_time->format('c'), $this->core->getConfig()->getDateTimeFormat()->getFormat('office_hours_queue'));
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
        if (in_array($removal_type, ['helped','self_helped'])) {
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

    public function firstTimeInQueueToday($time) {
        if (is_null($time)) {
            return true;
        }
        $one_day_ago = new \DateTime('tomorrow', $this->core->getConfig()->getTimezone());
        date_sub($one_day_ago, date_interval_create_from_date_string('1 days'));
        return DateUtils::parseDateTime($time, $this->core->getConfig()->getTimezone()) < $one_day_ago;
    }

    public function firstTimeInQueueThisWeek($time) {
        if (is_null($time)) {
            return true;
        }
        $one_week_ago = new \DateTime('tomorrow', $this->core->getConfig()->getTimezone());
        date_sub($one_week_ago, date_interval_create_from_date_string('5 days'));
        return DateUtils::parseDateTime($time, $this->core->getConfig()->getTimezone()) < $one_week_ago;
    }

    public function inQueue() {
        return $this->core->getQueries()->alreadyInAQueue();
    }

    public function getCurrentQueueCode() {
        return $this->current_queue_state['queue_code'];
    }

    public function isCurrentlyPaused() {
        return $this->current_queue_state['paused'];
    }

    public function getCurrentQueueStatus() {
        return $this->current_queue_state['current_state'];
    }

    public function getCurrentQueueLastHelped() {
        return $this->current_queue_state['last_time_in_queue'];
    }

    public function getCurrentQueueTimeIn() {
        return $this->current_queue_state['time_in'];
    }

    public function cleanForId($str) {
        return $this->core->getQueries()->getQueueId($str);
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






    public function getColorFromCode($code) {
        return $this->colors[$this->getIndexFromCode($code)];
    }



    public function isContactInfoEnabled() {
        return $this->core->getConfig()->getQueueContactInfo();
    }

    public function getQueueMessage() {
        return $this->core->getConfig()->getQueueMessage();
    }

    public function getQueueAnnouncementMessage() {
        return $this->core->getConfig()->getQueueAnnouncementMessage();
    }

    public function getNumberAheadInQueueThisWeek() {
        if ($this->firstTimeInQueueThisWeek($this->getCurrentQueueLastHelped())) {
            $time_in = DateUtils::parseDateTime($this->getCurrentQueueTimeIn(), $this->core->getConfig()->getTimezone());
        }
        else {
          //Check assuming their time_in is current time because then it assumes
          //everyone not helped this week is in front of them
            $time_in = $this->core->getDateTimeNow();
        }
        return $this->core->getQueries()->getNumberAheadInQueueThisWeek($this->getCurrentQueueCode(), $time_in);
    }

    public function getNumberAheadInQueueToday() {
        if ($this->firstTimeInQueueToday($this->getCurrentQueueLastHelped())) {
            $time_in = DateUtils::parseDateTime($this->getCurrentQueueTimeIn(), $this->core->getConfig()->getTimezone());
        }
        else {
          //Check assuming their time_in is current time because then it assumes
          //everyone not helped today is in front of them
            $time_in = $this->core->getDateTimeNow();
        }
        return $this->core->getQueries()->getNumberAheadInQueueToday($this->getCurrentQueueCode(), $time_in);
    }

    public function getAllQueuesEver() {
        return $this->core->getQueries()->getAllQueuesEver();
    }

    public function getQueueDataStudent() {
        return $this->core->getQueries()->getQueueDataStudent();
    }

    public function getQueueDataOverall() {
        return $this->core->getQueries()->getQueueDataOverall();
    }

    public function getQueueDataToday() {
        return $this->core->getQueries()->getQueueDataToday();
    }

    public function getQueueDataByQueue() {
        return $this->core->getQueries()->getQueueDataByQueue();
    }

    public function getQueueDataByWeekDay() {
        return $this->core->getQueries()->getQueueDataByWeekDay();
    }

    public function getQueueDataByWeekDayThisWeek() {
        return $this->core->getQueries()->getQueueDataByWeekDayThisWeek();
    }

    public function dayNumToDay($daynum): string {
        return $this->days[$daynum];
    }

    public function getQueueDataByWeekNumber() {
        return $this->core->getQueries()->getQueueDataByWeekNumber();
    }

    public function weekNumToDate($weeknum, $yearnum): string {
        $week_start = new DateTime();
        $week_start->setISODate($yearnum, $weeknum);
        return $week_start->format('Y-M-d');
    }

    public function statNiceName($name): string {
        return $this->niceNames[$name] ?? $name;
    }
}
