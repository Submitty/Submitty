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
        $this->core = $core;
    }

    public function onNewAnnouncement(array $event) {
        $recipients = $this->core->getQueries()->getAllUsersIds();
        $this->createAndSendNotification($event, $recipients);
    }

    public function onNewThread(array $event) {
        $recipients = $this->core->getQueries()->getAllUsersWithPreference("all_new_threads");
        $this->createAndSendNotification($event, $recipients);
    }

    public function onNewPost(array $event) {
        // notify parent authors, people who want to know about all posts, and people who want to know about new posts in threads they have posted.
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
        // on post deleted undeleted edited and merged
        $recipients = $this->core->getQueries()->getAllUsersWithPreference($event['preference']);
        $recipients[] = $event['recipient'];
        $recipients = array_unique($recipients);
        $this->createAndSendNotification($event,$recipients);
    }

    public function onGradeInquiryEvent(array $event) {
        $recipients = $event['recipients'];
        $this->createAndSendNotification($event,$recipients);
    }


    public function createAndSendNotification(array $event, array $recipients) {
        // if there are no recipients return
        if (empty($recipients)) {
            return;
        }
        $component = $event['component'];
        $metadata = $event['metadata'];
        $content = $event['content'];
        $current_user_id = $this->core->getUser()->getId();
        $notification = Notification::createNotification($this->core,$component,$metadata,$content,$current_user_id);
        $formatted_recipients = implode("','",$recipients);
        $formatted_recipients = "'{$formatted_recipients}'";
        $this->core->getQueries()->pushNotification($notification,$formatted_recipients);
    }
}
