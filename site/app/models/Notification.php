<?php

namespace app\models;

use app\libraries\Core;
use app\libraries\DateUtils;

/**
 * Class Notification
 *
 * @method void     setViewOnly($view_only)
 * @method void     setId($id)
 * @method void     setComponent($component)
 * @method void     setSeen($isSeen)
 * @method void     setElapsedTime($duration)
 * @method void     setCreatedAt($time)
 * @method void     setNotifyMetadata($metadata)
 * @method void     setNotifyContent($content)
 * @method void     setNotifySource($content)
 * @method void     setNotifyTarget($content)
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
    public function __construct(Core $core) {
        parent::__construct($core);
    }

    public static function createNotification(Core $core,array $event) {
        $instance = new self($core);
        $instance->setComponent($event['component']);
        $instance->setNotifyMetadata($event['metadata']);
        $instance->setNotifyContent($event['subject']);
        $instance->setNotifySource($event['sender_id']);
        $instance->setNotifyTarget($event['to_user_id']);
        return $instance;
    }

    public static function createViewOnlyNotification($core, $details) {
        $instance = new self($core);
        if (count($details) == 0) {
            return null;
        }
        $instance->setId($details['id']);
        $instance->setSeen($details['seen']);
        $instance->setComponent($details['component']);
        $instance->setElapsedTime($details['elapsed_time']);
        $instance->setCreatedAt($details['created_at']);
        $instance->setNotifyMetadata($details['metadata']);
        $instance->setNotifyContent($details['content']);
        return $instance;
    }

    /**
     * Returns the corresponding url based on metadata
     *
     * @param  Core     $core
     * @param  string   $metadata_json
     * @return string   $url
     */

    // added flag for links that go to sites handled by a router
    public static function getUrl($core, $metadata_json) {
        $metadata = json_decode($metadata_json, true);
        if (empty($metadata)) {
            return null;
        }

        if (!isset($metadata['url'])) {
            return $core->buildCourseUrl();
        }
        return $metadata['url'];
    }

    public static function getThreadIdIfExists($metadata_json) {
        $metadata = json_decode($metadata_json, true);
        if(is_null($metadata)) {
            return null;
        }
        $thread_id = $metadata['thread_id'] ?? -1;
        return $thread_id;
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
        return empty(json_decode($this->getNotifyMetadata()));
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
            $minutes = floor($elapsed_time / 60);
            if($minutes == 1)
                return "1 minute ago";
            else
                return "{$minutes} minutes ago";
        } else if($elapsed_time < 3600 * 24){
            $hours = floor($elapsed_time / 3600);
            if($hours == 1)
                return "1 hour ago";
            else
                return "{$hours} hours ago";
        } else {
            return date_format(DateUtils::parseDateTime($actual_time, $this->core->getConfig()->getTimezone()), "n/j g:i A");
        }
    }
}
