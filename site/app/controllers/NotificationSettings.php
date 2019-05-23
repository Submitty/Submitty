<?php

namespace app\controllers;

use app\libraries\Core;
use app\models\Notification;
use app\controllers\AbstractController;
use app\libraries\Output;

/**
 * Class NotificationSettings
 *
 */
class NotificationSettings extends AbstractController {

	const NOTIFICATION_SELECTIONS = [
	    'merge_threads',
        'all_new_threads',
        'all_new_posts',
        'all_modifications_forum',
        'reply_in_post_thread',
        'merge_threads_email',
        'all_new_threads_email',
        'all_new_posts_email',
        'all_modifications_forum_email',
        'reply_in_post_thread_email'
    ];

	public function __construct(Core $core) {
        parent::__construct($core);
    }

    public function run() {
        switch ($_REQUEST['page']) {
        	case 'alter_notifcation_settings':
        		$this->changeSettings();
        		break;
        }
    }

    public function changeSettings() {
    	//Change settings for the current user...
    	$new_settings = $_POST;
    	$result = ['error' => 'Notification settings could not be saved. Please try again.'];
    	if($this->validateNotificationSettings(array_keys($new_settings))) {
    		$values_not_sent = array_diff(self::NOTIFICATION_SELECTIONS, array_keys($new_settings));
	    	foreach(array_values($values_not_sent) as $value) {
	    		$new_settings[$value] = 'false';
	    	}
    		$this->core->getQueries()->updateNotificationSettings($new_settings);
    		$result = ['success' => 'Notification settings have been saved.'];
    	} 
    	$this->core->getOutput()->renderJson($result);
    	return $this->core->getOutput()->getOutput();
    }

    private function validateNotificationSettings($columns) {
    	if(count($columns) <= count(NotificationSettings::NOTIFICATION_SELECTIONS) && count(array_intersect($columns, self::NOTIFICATION_SELECTIONS)) == count($columns)) {
    		return true;
    	}
    	return false;
    }

}