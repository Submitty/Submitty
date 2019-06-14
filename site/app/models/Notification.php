<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\DateUtils;
use app\libraries\Utils;

/**
 * Class Notification
 *
 * @method void     setViewOnly($view_only)
 * @method void     setId($id)
 * @method void     setComponent($component)
 * @method void     setSeen($isSeen)
 * @method void     setElapsedTime($duration)
 * @method void     setCreatedAt($time)
 * @method void     setNotifyMetadata()
 * @method void     setNotifyContent()
 * @method void     setType($t)
 *
 * @method bool     isViewOnly()
 * @method int      getId()
 * @method string   getComponent()
 * @method bool     isSeen()
 * @method real     getElapsedTime()
 * @method string   getCreatedAt()
 * @method string   getCurrentUser()
 *
 * @method string   getNotifySource()
 * @method string   getNotifyTarget()
 * @method string   getNotifyContent()
 * @method string   getNotifyMetadata()
 * @method bool     getNotifyNotToSource()
 * @method string   getType()
 */
class Notification extends AbstractModel {
    /** @property @var bool Notification fetched from DB */
    protected $view_only;

    /** @property @var string Type of component */
    protected $component;
    /** @property @var string Current logged in user */
    protected $current_user;

    /** @property @var string Notification source user (can be null) */
    protected $notify_source;
    /** @property @var string Notification target user(s) (null implies all users) */
    protected $notify_target;
    /** @property @var string Notification text content */
    protected $notify_content;
    /** @property @var string Notification information about redirection link */
    protected $notify_metadata;
    /** @property @var bool Should $notify_source be ignored from $notify_target */
    protected $notify_not_to_source;

    /** @property @var int Notification ID */
    protected $id;
    /** @property @var bool Is notification already seen */
    protected $seen;
    /** @property @var real Time elapsed from creation of notification in secs */
    protected $elapsed_time;
    /** @property @var string Timestamp for creation of notification */
    protected $created_at;

    /** @property @var string Type of notification used for settings */
    protected $type;


    /**
     * Notifications constructor.
     *
     * @param Core  $core
     * @param array $details
     */
    public function __construct(Core $core, $details=array()) {
        parent::__construct($core);
        if (count($details) == 0) {
            return;
        }
        if(!empty($details['view_only'])){
            $this->setViewOnly(true);
            $this->setId($details['id']);
            $this->setSeen($details['seen']);
            $this->setComponent($details['component']);
            $this->setElapsedTime($details['elapsed_time']);
            $this->setCreatedAt($details['created_at']);
            $this->setNotifyMetadata($details['metadata']);
            $this->setNotifyContent($details['content']);
        } else {
            $this->setViewOnly(false);
            $this->setNotifyNotToSource(true);
            $this->setCurrentUser($this->core->getUser()->getId());
            $this->setComponent($details['component']);
            $this->setNotifyMetadata($details['metadata']);
            $this->setNotifyContent($details['content']);
            $this->setNotifySource($details['source']);
            $this->setNotifyTarget($details['target']);
        }
    }

    /**
     * Returns the corresponding url based on metadata
     *
     * @param  Core     core
     * @param  string   metadata
     * @return string   url
     */
    public static function getUrl($core, $metadata_json) {
        $metadata = json_decode($metadata_json);
        if(is_null($metadata)) {
            return null;
        }
        $parts = $metadata[0];
        $hash = $metadata[1] ?? null;
        return $core->buildUrl($parts, $hash);
    }

    public static function getThreadIdIfExists($metadata_json) {
        $metadata = json_decode($metadata_json, true);
        if(is_null($metadata)) {
            return null;
        }
        $thread_id = array_key_exists('thread_id', $metadata[0]) ? $metadata[0]['thread_id'] : -1;
        return $thread_id;
    }

    private function handleGrading($details) {
      $this->setType($details['type']);

      switch ($details['type']) {
        case 'grade_inquiry_creation':
          $this->actAsGradeInquiryCreation($details['gradeable_id'], $details['grader_id'], $details['submitter_id'], $details['who_id']);
          break;
        case 'grade_inquiry_reply':
          $this->actAsGradeInquiryReply($details['gradeable_id'], $details['grader_id'], $details['submitter_id'], $details['who_id']);
          break;
        default:
          return;
      }
    }

   private function handleStudent($details) {
     $this->setType($details['type']);

     switch ($details['type']) {
       case 'grade_inquiry_creation':
          $this->actAsGradeInquiryCreation($details['gradeable_id'], '', $details['submitter_id'], $details['who_id']);
          break;
       case 'grade_inquiry_reply':
          $this->actAsGradeInquiryReply($details['gradeable_id'], $details['grader_id'], $details['submitter_id'], '');
          break;
       default:
        return;
      }
   }

    private function actAsUpdatedAnnouncementNotification($thread_id, $thread_title) {
        $this->setNotifyMetadata(json_encode(array(array('component' => 'forum', 'page' => 'view_thread', 'thread_id' => $thread_id))));
        $this->setNotifyContent("Announcement: ".$thread_title);
        $this->setNotifySource($this->getCurrentUser());
        $this->setNotifyTarget(null);
    }


    private function actAsGradeInquiryCreation($gradeable_id, $grader_id, $submitter_id, $who_id){
      //notify a team member
      if($this->component == "student") {
        $this->setNotifyMetadata(json_encode(array(array('component' => 'student','gradeable_id' => $gradeable_id))));
        $this->setNotifyContent("A Member of your Team has Submitted a Grade Inquiry for ".$gradeable_id);
        $this->setNotifySource($submitter_id);
        $this->setNotifyTarget($who_id);
      }

      //notify a grader
      else if($this->component == "grading") {
        $this->setNotifyMetadata(json_encode(array(array('component' => 'grading', 'page' => 'electronic', 'action' => 'grade', 'gradeable_id' => $gradeable_id, 'who_id' => $who_id))));
        $this->setNotifyContent("New Grade Inquiry for ".$gradeable_id);
        $this->setNotifySource($submitter_id);
        $this->setNotifyTarget($grader_id);
       }
     }

    private function actAsGradeInquiryReply($gradeable_id, $grader_id, $submitter_id, $who_id) {
      $this->setNotifyContent("New Grade Inquiry Reply for ".$gradeable_id);
      //notify a student
      if($this->component == "student") {
        $this->setNotifyMetadata(json_encode(array(array('component' => 'student','gradeable_id' => $gradeable_id))));
        $this->setNotifySource($grader_id);
        $this->setNotifyTarget($submitter_id);
      }
      //notify a grader
      else if($this->component == "grading"){
        $this->setNotifyMetadata(json_encode(array(array('component' => 'grading', 'page' => 'electronic', 'action' => 'grade', 'gradeable_id' => $gradeable_id, 'who_id' => $who_id))));
        $this->setNotifySource($submitter_id);
        $this->setNotifyTarget($grader_id);
      }
    }

    /**
     * Trim long $message upto 40 character and filter newline
     *
     * @param string $message
     * @return $trimmed_message
     */
    public static function textShortner($message) {
        $max_length = 40;
        $message = str_replace("\n", " ", $message);
        if(strlen($message) > $max_length) {
            $message = substr($message, 0, $max_length - 3) . "...";
        }
        return $message;
    }

    public function hasEmptyMetadata() {
        return count(json_decode($this->getNotifyMetadata())) == 0;
    }

    /**
     * Returns relative time if time is in last 24 hours
     * else returns absolute time
     *
     * @return string $formatted_time
     */
    public function getNotifyTime() {
        $elapsed_time = $this->getElapsedTime();
        $actual_time = $this->getCreatedAt();
        if($elapsed_time < 60){
            return "Less than a minute ago";
        } else if($elapsed_time < 3600){
            $minutes = floor($elapsed_time/60);
            if($minutes == 1)
                return "1 minute ago";
            else
                return "{$minutes} minutes ago";
        } else if($elapsed_time < 3600*24){
            $hours = floor($elapsed_time/3600);
            if($hours == 1)
                return "1 hour ago";
            else
                return "{$hours} hours ago";
        } else {
            return date_format(DateUtils::parseDateTime($actual_time, $this->core->getConfig()->getTimezone()), "n/j g:i A");
        }
    }
}
