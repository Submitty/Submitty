<?php

namespace app\models;

use app\libraries\Core;

/**
 * Class Notification
 *
 * @method string getComponent()
 * @method string getCurrentUser()
 * @method string getNotifySource()
 * @method string getNotifyTarget()
 * @method string getNotifyContent()
 * @method string getNotifyMetadata()
 * @method bool getNotifyNotToSource()
 */
class Notification extends AbstractModel {

    /** @property @var string Type of notification */
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
    /** @property @var string Should $notify_source be ignored from $notify_target */
    protected $notify_not_to_source;

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
        $this->setNotifyNotToSource(true);
        $this->setCurrentUser($this->core->getUser()->getId());
        $this->setComponent($details['component']);
        switch ($this->getComponent()) {
            case 'forum':
                $this->handleForum($details);
                break;
            default:
                // Prevent notification to be pushed in database
                $this->setComponent("invalid");
                break;
        }
    }

    /**
     * Handles notifications related to forum
     *
     * @param array $details
     */
    private function handleForum($details) {
        switch ($details['type']) {
            case 'new_announcement':
                $this->setNotifyMetadata(json_encode(array('component' => 'forum', 'page' => 'view_thread', 'thread_id' => $details['thread_id'])));
                $this->setNotifyContent("New Announcement: ".$details['thread_title']);
                $this->setNotifySource($this->getCurrentUser());
                $this->setNotifyTarget(null);
                break;
            case 'updated_announcement':
                $this->setNotifyMetadata(json_encode(array('component' => 'forum', 'page' => 'view_thread', 'thread_id' => $details['thread_id'])));
                $this->setNotifyContent("Announcement: ".$details['thread_title']);
                $this->setNotifySource($this->getCurrentUser());
                $this->setNotifyTarget(null);
                break;
            case 'reply':
                // TODO: Redirect to post itself
                $this->setNotifyMetadata(json_encode(array('component' => 'forum', 'page' => 'view_thread', 'thread_id' => $details['thread_id'])));
                $this->setNotifyContent("Reply: Your post '".$this->textShortner($details['post_content'])."' got new a reply from ".$this->getCurrentUser());
                $this->setNotifySource($this->getCurrentUser());
                $this->setNotifyTarget($details['reply_to']);
                break;
            case 'merge_thread':
                // TODO: Redirect to post itself
                $this->setNotifyMetadata(json_encode(array('component' => 'forum', 'page' => 'view_thread', 'thread_id' => $details['parent_thread_id'])));
                $this->setNotifyContent("Thread Merged: '".$this->textShortner($details['child_thread_title'])."' got merged into '".$this->textShortner($details['parent_thread_title'])."'");
                $this->setNotifySource($this->getCurrentUser());
                $this->setNotifyTarget($details['reply_to']);
                break;
            case 'edited':
                // TODO: Redirect to post itself(if exists)
                $this->setNotifyMetadata(json_encode(array('component' => 'forum', 'page' => 'view_thread', 'thread_id' => $details['thread_id'])));
                $this->setNotifyContent("Update: Your thread/post '".$this->textShortner($details['post_content'])."' got an edit from ".$this->getCurrentUser());
                $this->setNotifySource($this->getCurrentUser());
                $this->setNotifyTarget($details['reply_to']);
                break;
            case 'deleted':
                $this->setNotifyMetadata(json_encode(array('component' => 'forum', 'page' => 'view_thread', 'thread_id' => $details['thread_id'])));
                $this->setNotifyContent("Deleted: Your thread/post '".$this->textShortner($details['post_content'])."' was deleted by ".$this->getCurrentUser());
                $this->setNotifySource($this->getCurrentUser());
                $this->setNotifyTarget($details['reply_to']);
                break;
            case 'undeleted':
                // TODO: Redirect to post itself(if exists)
                $this->setNotifyMetadata(json_encode(array('component' => 'forum', 'page' => 'view_thread', 'thread_id' => $details['thread_id'])));
                $this->setNotifyContent("Undeleted: Your thread/post '".$this->textShortner($details['post_content'])."' has been undeleted by ".$this->getCurrentUser());
                $this->setNotifySource($this->getCurrentUser());
                $this->setNotifyTarget($details['reply_to']);
                break;
            default:
                return;
        }
    }


    /**
     * Push to generated notifcation information to database
     */
    public function pushNotificationToDatabase() {
        if(empty($this->getNotifyTarget())) {
            // Notify all users
            $this->core->getQueries()->pushNotificationToAllUserInCourse(
                    $this->getNotifySource(),
                    $this->getComponent(),
                    $this->getNotifyMetadata(),
                    $this->getNotifyContent(),
                    $this->getNotifyNotToSource()
                );
        } else {
            $this->core->getQueries()->pushNotificationToAUser(
                    $this->getNotifySource(),
                    $this->getComponent(),
                    $this->getNotifyMetadata(),
                    $this->getNotifyContent(),
                    $this->getNotifyTarget(),
                    $this->getNotifyNotToSource()
                );
        }
    }

    /**
     * Trim long $message upto 40 character and filter newline
     *
     * @param string $message
     * @return $trimmed_message
     */
    private function textShortner($message) {
        return mb_strimwidth(str_replace("\n", " ", $message), 0, 40, "...");
    }
}
