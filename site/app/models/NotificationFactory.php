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

    // ***********************************FORUM NOTIFICATIONS***********************************

    /**
     * @param array $event
     */
    public function onNewAnnouncement(array $event) {
        $recipients = $this->core->getQueries()->getAllUsersIds();
        $notifications = $this->createNotificationsArray($event,$recipients);
        $this->sendNotifications($notifications);
        $emails =$this->createEmailsArray($event,$recipients);
        $this->sendEmails($emails);
    }

    /**
     * @param array $event
     */
    public function onNewThread(array $event) {
        $recipients = $this->core->getQueries()->getAllUsersWithPreference("all_new_threads");
        $notifications = $this->createNotificationsArray($event,$recipients);
        $this->sendNotifications($notifications);
        $recipients = $this->core->getQueries()->getAllUsersWithPreference("all_new_threads_email");
        $emails =$this->createEmailsArray($event,$recipients);
        $this->sendEmails($emails);
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
        $thread_authors_notification_preference = $this->core->getQueries()->getAllThreadAuthors($thread_id,"reply_in_post_thread");
        $notification_recipients = array_unique(array_merge($parent_authors, $users_with_notification_preference, $thread_authors_notification_preference));
        $notifications = $this->createNotificationsArray($event,$notification_recipients);
        $this->sendNotifications($notifications);

        $users_with_email_preference = $this->core->getQueries()->getAllUsersWithPreference("all_new_posts_email");
        $thread_authors_email_preference = $this->core->getQueries()->getAllThreadAuthors($thread_id,"reply_in_post_thread_email");
        $email_recipients = array_unique(array_merge($parent_authors, $users_with_email_preference, $thread_authors_email_preference));
        $emails =$this->createEmailsArray($event,$email_recipients);
        $this->sendEmails($emails);

    }

    /**
     * handles the event of a post deleted, undeleted, edited and merged
     * @param array $event
     */
    public function onPostModified(array $event) {
        $notification_recipients = $this->core->getQueries()->getAllUsersWithPreference($event['preference']);
        $notification_recipients[] = $event['recipient'];
        $notification_recipients = array_unique($notification_recipients);
        $notifications = $this->createNotificationsArray($event, $notification_recipients);
        $this->sendNotifications($notifications);

        $email_recipients =  $this->core->getQueries()->getAllUsersWithPreference($event['preference'].'_email');
        $email_recipients[] = $event['recipient'];
        $email_recipients = array_unique($email_recipients);
        $emails = $this->createEmailsArray($event,$email_recipients);
        $this->sendEmails($emails);
    }

    // ***********************************HELPERS***********************************

    /**
     * @param $event
     * @param $recipients
     * @return array
     */
    private function createNotificationsArray($event, $recipients) {
        $event['sender_id'] = $this->core->getUser()->getId();
        $notifications = array();
        foreach ($recipients as $recipient) {
            $event['to_user_id'] = $recipient;
            $notifications[] = Notification::createNotification($this->core,$event);
        }
        return $notifications;
    }

    /**
     * @param $event
     * @param $recipients
     * @return array of email objects
     */
    private function createEmailsArray($event, $recipients) {
        $emails = array();
        foreach ($recipients as $recipient) {
            $details = [
                'to_user_id' => $recipient,
                'subject' => $event['subject'],
                'body' => $event['content']
            ];
            $emails[] = new Email($this->core,$details);
        }
        return $emails;
    }

    // ***********************************SENDERS***********************************
    /**
     * @param array $event
     * @param array $recipients
     */
    public function sendNotifications(array $notifications) {
        if (empty($notifications)) {
            return;
        }

        // parameterize notification array
        foreach ($notifications as $notification) {
            $flattened_notifications[] = $notification->getComponent();
            $flattened_notifications[] = $notification->getNotifyMetadata();
            $flattened_notifications[] = $notification->getNotifyContent();
            $flattened_notifications[] = $notification->getNotifySource();
            $flattened_notifications[] = $notification->getNotifyTarget();
        }
        $this->core->getQueries()->insertNotifications($flattened_notifications,count($notifications));
    }

    /**
     * prepare array of Email objects as param array
     * @param array $emails
     */
    public function sendEmails(array $emails) {
        if (empty($emails)) {
            return;
        }
        // parameterize email array
        foreach ($emails as $email) {
            $flattened_emails[] = $email->getRecipient();
            $flattened_emails[] = $email->getSubject();
            $flattened_emails[] = $email->getBody();
            $flattened_emails[] = $email->getUserId();
        }
        $this->core->getQueries()->insertEmails($flattened_emails,count($emails));
    }
}
