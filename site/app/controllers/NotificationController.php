<?php

namespace app\controllers;

use app\libraries\Core;
use app\models\Notification;
use app\controllers\AbstractController;
use app\libraries\Output;

/**
 * Class NotificationController
 *
 */
class NotificationController extends AbstractController {

	const NOTIFICATION_SELECTIONS = [
	    'merge_threads',
        'all_new_threads',
        'all_new_posts',
        'all_modifications_forum',
        'reply_in_post_thread'
    ];

	const EMAIL_SELECTIONS = [
        'merge_threads_email',
        'all_new_threads_email',
        'all_new_posts_email',
        'all_modifications_forum_email',
        'reply_in_post_thread_email'
    ];

	protected $selections;

	public function __construct(Core $core) {
        parent::__construct($core);
        $this->selections = self::NOTIFICATION_SELECTIONS;
        if ($this->core->getConfig()->isEmailEnabled()) {
            $this->selections = array_merge($this->selections,self::EMAIL_SELECTIONS);
        }
    }

    public function run() {
        switch ($_REQUEST['page']) {
            case 'notifications':
                $this->notificationsHandler();
                break;
            case 'notification_settings':
                $this->viewNotificationSettings();
                break;
        	case 'alter_notification_settings':
        		$this->changeSettings();
        		break;
        }
    }

    public function notificationsHandler() {
        $user_id = $this->core->getUser()->getId();
        if (!empty($_GET['action'])) {
            if ($_GET['action'] == 'open_notification' && !empty($_GET['nid']) && is_numeric($_GET['nid']) && $_GET['nid'] >= 1) {
                $metadata = $this->core->getQueries()->getNotificationInfoById($user_id, $_GET['nid'])['metadata'];
                if (!$_GET['seen']) {
                    $thread_id = Notification::getThreadIdIfExists($metadata);
                    $this->core->getQueries()->markNotificationAsSeen($user_id, $_GET['nid'], $thread_id);
                }
                $this->core->redirect(Notification::getUrl($this->core, $metadata));
            }
            elseif ($_GET['action'] == 'mark_as_seen' && !empty($_GET['nid']) && is_numeric($_GET['nid']) && $_GET['nid'] >= 1) {
                $this->core->getQueries()->markNotificationAsSeen($user_id, $_GET['nid']);
                $this->core->redirect($this->core->buildUrl(array('component' => 'notification', 'page' => 'notifications')));
            }
            elseif ($_GET['action'] == 'mark_all_as_seen') {
                $this->core->getQueries()->markNotificationAsSeen($user_id, -1);
                $this->core->redirect($this->core->buildUrl(array('component' => 'notification', 'page' => 'notifications')));
            }
            else {
                $this->core->redirect($this->core->buildUrl(array('component' => 'notification', 'page' => 'notifications')));
            }
        }
        else {
            // Show Notifications
            $show_all = (!empty($_GET['show_all']) && $_GET['show_all'])?true:false;
            $notifications = $this->core->getQueries()->getUserNotifications($user_id, $show_all);
            $current_course = $this->core->getConfig()->getCourse();
            $notification_saves = $this->core->getUser()->getNotificationSettings();
            $this->core->getOutput()->renderOutput('Notification', 'showNotifications', $current_course, $show_all, $notifications, $notification_saves);
        }
    }

    public function viewNotificationSettings() {
        $this->core->getOutput()->renderOutput(
            'Notification',
            'showNotificationSettings',
            $this->core->getUser()->getNotificationSettings()
        );
    }

    public function changeSettings() {
        //Change settings for the current user...
        $new_settings = $_POST;

        if ($this->validateNotificationSettings(array_keys($new_settings))) {
            $values_not_sent = array_diff($this->selections, array_keys($new_settings));
            foreach(array_values($values_not_sent) as $value) {
                $new_settings[$value] = 'false';
            }
            $this->core->getQueries()->updateNotificationSettings($new_settings);
            return $this->core->getOutput()->renderJsonSuccess('Notification settings have been saved.');
        }
        else {
            return $this->core->getOutput()->renderJsonFail('Notification settings could not be saved. Please try again.');
        }
    }

    private function validateNotificationSettings($columns) {
        if (count($columns) <= count($this->selections) && count(array_intersect($columns, $this->selections)) == count($columns)) {
            return true;
        }
        return false;
    }
}
