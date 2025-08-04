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
 * @method void setSemester(string $semester)
 * @method void setCourse(string $course)

 *
 * @method bool     isViewOnly()
 * @method int      getId()
 * @method string   getComponent()
 * @method bool     isSeen()
 * @method float     getElapsedTime()
 * @method string   getCreatedAt()
 * @method string   getCurrentUser()
 *
 * @method string   getNotifySource()
 * @method string   getNotifyTarget()
 * @method string   getNotifyContent()
 * @method string   getNotifyMetadata()
 * @method bool     getNotifyNotToSource()
 * @method string   getType()
 * @method string|null getSemester()
 * @method string|null getCourse()
 * 
 */
class Notification extends AbstractModel implements \JsonSerializable {
    /** @prop
     * @var bool Notification fetched from DB */
    protected $view_only;

    /** @prop
     * @var string Type of component */
    protected $component;
    /** @prop
     * @var string Current logged in user */
    protected $current_user;

    /** @prop
     * @var string Notification source user (can be null) */
    protected $notify_source;
    /** @prop
     * @var string Notification target user(s) (null implies all users) */
    protected $notify_target;
    /** @prop
     * @var string Notification text content */
    protected $notify_content;
    /** @prop
     * @var string Notification information about redirection link */
    protected $notify_metadata;
    /** @prop
     * @var bool Should $notify_source be ignored from $notify_target */
    protected $notify_not_to_source;

    /** @prop
     * @var string|null Semester for this notification */
    protected ?string $semester = null;

    /** @prop
     * @var string|null Course for this notification */
    protected ?string $course = null;

    /** @prop
     * @var int Notification ID */
    protected $id;
    /** @prop
     * @var bool Is notification already seen */
    protected $seen;
    /** @prop
     * @var real Time elapsed from creation of notification in secs */
    protected $elapsed_time;
    /** @prop
     * @var string Timestamp for creation of notification */
    protected $created_at;

    /** @prop
     * @var string Type of notification used for settings */
    protected $type;


    /**
     * Notifications constructor.
     *
     * @param Core  $core
     */
    public function __construct(Core $core) {
        parent::__construct($core);
    }

    public static function createNotification(Core $core, array $event) {
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
        if (isset($details['semester'])) {
            $instance->setSemester($details['semester']);
        }

        if (isset($details['course'])) {
            $instance->setCourse($details['course']);
        }
        return $instance;
    }

    /**
     * Returns the corresponding url based on metadata
     */
    public static function getUrl(Core $core, string $metadata_json): ?string {
        $metadata = json_decode($metadata_json, true);
        if (empty($metadata)) {
            return null;
        }

        if (!isset($metadata['url'])) {
            return $core->buildCourseUrl();
        }
        return $metadata['url'];
    }

    public static function getThreadIdIfExists(string $metadata_json): ?int {
        $metadata = json_decode($metadata_json, true);
        if (is_null($metadata)) {
            return null;
        }
        return $metadata['thread_id'] ?? -1;
    }

    public function hasEmptyMetadata(): bool {
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
        if ($elapsed_time < 60) {
            return "Less than a minute ago";
        }
        elseif ($elapsed_time < 3600) {
            $minutes = floor($elapsed_time / 60);
            if ($minutes == 1) {
                return "1 minute ago";
            }
            else {
                return "{$minutes} minutes ago";
            }
        }
        elseif ($elapsed_time < 3600 * 24) {
            $hours = floor($elapsed_time / 3600);
            if ($hours == 1) {
                return "1 hour ago";
            }
            else {
                return "{$hours} hours ago";
            }
        }
        else {
            return DateUtils::convertTimeStamp($this->core->getUser(), $actual_time, $this->core->getConfig()->getDateTimeFormat()->getFormat('notification'));
        }
    }

    public function jsonSerialize(): array {
        $base_url = '';

        if ($this->getNotifyMetadata() !== null) {
            $semester = $this->semester;
            $course = $this->course;

            if (!empty($semester) && !empty($course)) {
                $base_url = $this->core->buildUrl(['courses', $semester, $course, 'notifications', $this->getId()]);
            }
            else {
                $base_url = $this->core->buildUrl(['home']);
            }
        }
        else {
            $base_url = $this->core->buildUrl(['home']);
        }

        $notification_url = $base_url . '?seen=' . ($this->isSeen() ? '1' : '0');

        return [
            'id' => $this->getId(),
            'component' => $this->getComponent(),
            'metadata' => $this->getNotifyMetadata(),
            'content' => $this->getNotifyContent(),
            'seen' => $this->isSeen(),
            'elapsed_time' => $this->getElapsedTime(),
            'created_at' => $this->getCreatedAt(),
            'notify_time' => $this->getNotifyTime(),
            'semester' => $this->semester,
            'course' => $this->course,
            'notification_url' => $notification_url
        ];
    }
}
