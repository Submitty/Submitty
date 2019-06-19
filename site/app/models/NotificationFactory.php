<?php


namespace app;

use app\libraries\Core;
use app\models\Notification;


class NotificationFactory {

    /**
     * @var Core $core
     */
    protected $core;

    public function __construct(Core $core) {
        $this->$core = $core;
    }

    public function onNewAnnouncement(array $event) {
        $recipients = $this->core->getQueries()->getAllOtherUsers($this->core->getUser()->getId());
        $this->createAndSendNotification($event, $recipients);
    }

    public function onNewThread(array $event) {
        $recipients = $this->core->getQueries()->getAllUsersWithPreference("all_new_thread");
        $this->createAndSendNotification($event, $recipients);
    }

    public function onNewPost(array $event) {
        $current_user_id = $this->core->getUser()->getId();
        $post_id = $event["post_id"];
        $thread_id = $event["thread_id"];
        $parent_authors = $this->core->getQueries()->getAllParentAuthors($current_user_id,$post_id);
        $users_with_preference = $this->core->getQueries()->getAllUsersWithPreference("all_new_posts");
        $thread_authors = $this->core->getQueries()->getAllThreadAuthors($thread_id);
        $recipients = array_unique(array_merge($parent_authors, $users_with_preference, $thread_authors));
        $this->createAndSendNotification($event, $recipients);
    }

    public function onPostModified(array $event) {
        $recipients = array_unique($this->core->getQueries()->getAllUsersWithPreference("all_modification_forum"));
        $recipients[] = $event['recipients'];
        $recipients = array_unique($recipients);
        $this->createAndSendNotification($event,$recipients);
    }

    public function onThreadMerged(array $event) {
        $recipients = array_unique($this->core->getQueries()->getAllUsersWithPreference("merge_threads"));
        $recipients[] = $event['recipients'];
        $recipients = array_unique($recipients);
        $this->createAndSendNotification($event,$recipients);
    }

    public function onGradeInquiryEvent(array $event) {
        $recipients = $event['recipients'];
        $this->createAndSendNotification($event,$recipients);
    }


    public function createAndSendNotification(array $event, array $recipients) {
        $metadata = $event['metadata'];
        $content = $event['content'];
        $current_user_id = $this->core->getUser()->getId();
        $notification = Notification::createNotification($this->core,$metadata,$content,$current_user_id);
        $this->core->getQueries()->pushNotification($notification,$recipients);
    }
}
