<?php

namespace app\libraries;

use app\models\Email;
use app\models\Notification;
use app\models\User;
use LogicException;

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
    public function onNewAnnouncement(array $event): void {
        $recipients = $this->core->getQueries()->getAllUsersIds();
        $notifications = $this->createNotificationsArray($event, $recipients);
        $this->sendNotifications($notifications);
        if ($this->core->getConfig()->isEmailEnabled()) {
            $emails = $this->createEmailsArray($event, $recipients, true);
            $this->sendEmails($emails);
        }
    }

    /**
     * @param array $event
     */
    public function onNewThread(array $event): void {
        $recipients = $this->core->getQueries()->getAllUsersWithPreference("all_new_threads");
        $recipients[] = $this->core->getUser()->getId();
        $recipients = array_unique($recipients);
        $notifications = $this->createNotificationsArray($event, $recipients);
        $this->sendNotifications($notifications);
        if ($this->core->getConfig()->isEmailEnabled()) {
            $recipients = $this->core->getQueries()->getAllUsersWithPreference("all_new_threads_email");
            $recipients[] = $this->core->getUser()->getId();
            $recipients = array_unique($recipients);
            $emails = $this->createEmailsArray($event, $recipients, true);
            $this->sendEmails($emails);
        }
    }

    /**
     * notifies parent authors, people who want to know about all posts, and people who want to know about new posts in threads they have posted
     * @param array $event
     */
    public function onNewPost(array $event): void {
        $current_user_id = $this->core->getUser()->getId();
        $post_id = $event["post_id"];
        $thread_id = $event["thread_id"];

        $parent_authors = $this->core->getQueries()->getAllParentAuthors($current_user_id, $post_id);
        $users_with_notification_preference = $this->core->getQueries()->getAllUsersWithPreference("all_new_posts");
        $thread_authors_notification_preference = $this->core->getQueries()->getAllThreadAuthors($thread_id, "reply_in_post_thread");
        $notification_recipients = array_merge($parent_authors, $users_with_notification_preference, $thread_authors_notification_preference);
        $notification_recipients[] = $current_user_id;
        $notification_recipients = array_unique($notification_recipients);
        $notifications = $this->createNotificationsArray($event, $notification_recipients);
        $this->sendNotifications($notifications);

        if ($this->core->getConfig()->isEmailEnabled()) {
            $users_with_email_preference = $this->core->getQueries()->getAllUsersWithPreference("all_new_posts_email");
            $thread_authors_email_preference = $this->core->getQueries()->getAllThreadAuthors($thread_id, "reply_in_post_thread_email");
            $email_recipients = array_merge($parent_authors, $users_with_email_preference, $thread_authors_email_preference);
            $email_recipients[] = $current_user_id;
            $email_recipients = array_unique($email_recipients);
            $emails = $this->createEmailsArray($event, $email_recipients, true);
            $this->sendEmails($emails);
        }
    }

    /**
     * handles the event of a post deleted, undeleted, edited and merged
     * @param array $event
     */
    public function onPostModified(array $event): void {
        $notification_recipients = $this->core->getQueries()->getAllUsersWithPreference($event['preference']);
        $notification_recipients[] = $event['recipient'];
        $notification_recipients[] = $this->core->getUser()->getId();
        $notification_recipients = array_unique($notification_recipients);
        $notifications = $this->createNotificationsArray($event, $notification_recipients);
        $this->sendNotifications($notifications);

        if ($this->core->getConfig()->isEmailEnabled()) {
            $email_recipients =  $this->core->getQueries()->getAllUsersWithPreference($event['preference'] . '_email');
            $email_recipients[] = $event['recipient'];
            $email_recipients[] = $this->core->getUser()->getId();
            $email_recipients = array_unique($email_recipients);
            $emails = $this->createEmailsArray($event, $email_recipients, true);
            $this->sendEmails($emails);
        }
    }
    // ***********************************TEAM NOTIFICATIONS***********************************

    /**
     * checks whether $recipients have correct team settings and sends the notification and email.
     * @param array $event
     * @param array $recipients
     */
    public function onTeamEvent(array $event, array $recipients): void {
        $current_user_id = $this->core->getUser()->getId();
        $notification_recipients = [$current_user_id];
        $email_recipients = [$current_user_id];
        $users_settings = $this->core->getQueries()->getUsersNotificationSettings($recipients);
        foreach ($recipients as $recipient) {
            $user_settings_row = array_values(array_filter($users_settings, function ($v) use ($recipient) {
                return $v['user_id'] === $recipient;
            }));
            if (!empty($user_settings_row)) {
                $user_settings_row = $user_settings_row[0];
            }
            $user_settings = User::constructNotificationSettings($user_settings_row);
            if ($user_settings[$event['type']]) {
                $notification_recipients[] = $recipient;
            }
            if ($user_settings[$event['type'] . '_email']) {
                $email_recipients[] = $recipient;
            }
        }
        $notifications = $this->createNotificationsArray($event, $notification_recipients);
        $this->sendNotifications($notifications);
        if ($this->core->getConfig()->isEmailEnabled()) {
            $emails = $this->createEmailsArray($event, $email_recipients, false);
            $this->sendEmails($emails);
        }
    }

    // ***********************************HELPERS***********************************

    /**
     * @param array $event
     * @param array $recipients
     * @return array
     */
    private function createNotificationsArray(array $event, array $recipients): array {
        $event['sender_id'] = $this->core->getUser()->getId();
        $notifications = array();
        foreach ($recipients as $recipient) {
            $event['to_user_id'] = $recipient;
            $notifications[] = Notification::createNotification($this->core, $event);
        }
        return $notifications;
    }

    /**
     * @param array $event
     * @param array $recipients
     * @param bool $author
     * @return array of email objects
     */
    private function createEmailsArray(array $event, array $recipients, bool $author): array {
        $emails = array();
        foreach ($recipients as $recipient) {
            //Checks if a url is in metadata and sets $relevant_url null or that url
            $metadata = json_decode($event['metadata'], true);
            $relevant_url = null;
            if (array_key_exists("url", $metadata)) {
                $relevant_url = $metadata["url"];
            }

            $details = [
                'to_user_id' => $recipient,
                'subject' => $event['subject'],
                'body' => $event['content'],
                'relevant_url' => $relevant_url,
                'author' => $author
            ];
            $emails[] = new Email($this->core, $details);
        }
        return $emails;
    }

    // ***********************************SENDERS***********************************
    /**
     * @param array $notifications
     */
    public function sendNotifications(array $notifications): void {
        if (empty($notifications)) {
            return;
        }

        // parametrize notification array
        $current_user = $this->core->getUser();
        $flattened_notifications = [];
        foreach ($notifications as $notification) {
            // check if user is in the null section
            if (!$this->core->getQueries()->checkStudentActiveInCourse($notification->getNotifyTarget(), $this->core->getConfig()->getCourse(), $this->core->getConfig()->getSemester())) {
                continue;
            }
            if ($notification->getNotifyTarget() != $current_user->getId() || $current_user->getNotificationSetting('self_notification')) {
                $flattened_notifications[] = $notification->getComponent();
                $flattened_notifications[] = $notification->getNotifyMetadata();
                $flattened_notifications[] = $notification->getNotifyContent();
                $flattened_notifications[] = $notification->getNotifySource();
                $flattened_notifications[] = $notification->getNotifyTarget();
            }
        }
        if (!empty($flattened_notifications)) {
            // some notifications may not have been added to the flattened notifications
            // so to calculate the number of notifications we must use flattened notifications
            $this->core->getQueries()->insertNotifications($flattened_notifications, count($flattened_notifications) / 5);
        }
    }

    /**
     * prepare array of Email objects as param array
     * @param array $emails
     */
    public function sendEmails(array $emails): void {
        if (!$this->core->getConfig()->isEmailEnabled()) {
            throw new LogicException("Email is not enabled");
        }
        if (empty($emails)) {
            return;
        }
        // parametrize email array
        $current_user = $this->core->getUser();
        $flattened_emails = [];
        foreach ($emails as $email) {
            // check if user is in the null section
            if (!$this->core->getQueries()->checkStudentActiveInCourse($email->getUserId(), $this->core->getConfig()->getCourse(), $this->core->getConfig()->getSemester())) {
                continue;
            }
            if ($email->getUserId() != $current_user->getId() || $current_user->getNotificationSetting('self_notification_email')) {
                $flattened_emails[] = $email->getSubject();
                $flattened_emails[] = $email->getBody();
                $flattened_emails[] = $email->getUserId();
            }
        }
        if (!empty($flattened_emails)) {
            $this->core->getQueries()->insertEmails($flattened_emails, count($flattened_emails) / 3);
        }
    }
}
