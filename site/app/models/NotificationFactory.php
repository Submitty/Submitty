<?php

namespace app;

use app\libraries\Core;
use app\models\Email;
use app\models\Notification;

/**
 * A factory class that will handle all notification events and send notifications and emails accordingly
 * Class NotificationFactory
 * @package app
 */
class NotificationFactory {

    /**
     * @var Core $core
     */
    protected $core;

    public function __construct(Core $core) {
        $this->core = $core;
    }

    /**
     * @param array $event
     */
    public function onNewAnnouncement(array $event) {
        $recipients = $this->core->getQueries()->getAllUsersIds();
        $this->createAndSendNotifications($event, $recipients);
        $this->createAndSendEmails($event, $recipients);
    }

    /**
     * @param array $event
     */
    public function onNewThread(array $event) {
        $recipients = $this->core->getQueries()->getAllUsersWithPreference("all_new_threads");
        $this->createAndSendNotifications($event, $recipients);
        $recipients = $this->core->getQueries()->getAllUsersWithPreference("all_new_threads_emails");
        $this->createAndSendEmails($event,$recipients);
    }

    /**
     * notifies parent authors, people who want to know about all posts, and people who want to know about new posts in threads they have posted
     * @param array $event
     */
    public function onNewPost(array $event) {
        $current_user_id = $this->core->getUser()->getId();
        $post_id = $event["post_id"];
        $thread_id = $event["thread_id"];

        $parent_authors = $this->core->getQueries()->getAllParentAuthors($current_user_id,$post_id);
        $users_with_notification_preference = $this->core->getQueries()->getAllUsersWithPreference("all_new_posts");
        $thread_authors_notification_preference = $this->core->getQueries()->getAllThreadAuthors($thread_id,"reply_in_thread");
        $notification_recipients = array_unique(array_merge($parent_authors, $users_with_notification_preference, $thread_authors_notification_preference));
        $this->createAndSendNotifications($event, $notification_recipients);

        $users_with_email_preference = $this->core->getQueries()->getAllUsersWithPreference("all_new_posts_email");
        $thread_authors_email_preference = $this->core->getQueries()->getAllThreadAuthors($thread_id,"reply_in_thread_email");
        $email_recipients = array_unique(array_merge($parent_authors, $users_with_email_preference, $thread_authors_email_preference));
        $this->createAndSendEmails($event, $email_recipients);

    }

    /**
     * handles the event of a post deleted, undeleted, edited and merged
     * @param array $event
     */
    public function onPostModified(array $event) {
        //
        $notification_recipients = $this->core->getQueries()->getAllUsersWithPreference($event['preference']);
        $notification_recipients[] = $event['recipient'];
        $notification_recipients = array_unique($notification_recipients);
        $this->createAndSendNotifications($event,$notification_recipients);

        $email_recipients =  $this->core->getQueries()->getAllUsersWithPreference($event['preference'].'_email');
        $email_recipients[] = $event['recipient'];
        $email_recipients = array_unique($email_recipients);
        $this->createAndSendEmails($event,$email_recipients);
    }

    /**
     * @param array $event
     */
    public function onGradeInquiryEvent(array $event) {
        // TODO::Allow users to have preference on grade inquiry events
        $recipients = $event['recipients'];
        $this->createAndSendNotifications($event,$recipients);
        $this->createAndSendEmails($event,$recipients);
    }


    /**
     * @param array $event
     * @param array $recipients
     */
    public function createAndSendNotifications(array $event, array $recipients) {
        // if there are no recipients return
        if (empty($recipients)) {
            return;
        }
        $event['user_id'] = $this->core->getUser()->getId();
        $notification = Notification::createNotification($this->core,$event);
        $this->core->getQueries()->pushNotifications($notification,$recipients);
    }

    /**
     * @param array $event
     * @param array $recipients
     */
    public function createAndSendEmails(array $event, array $recipients) {
        if (empty($recipients)) {
            return;
        }
        $event["user_id"] = $this->core->getUser()->getId();
        $email = new Email($this->core,$event);
        $this->core->getQueries()->pushEmails($email,$recipients);


    }
}
