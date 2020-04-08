<?php

namespace app\controllers;

use app\libraries\Core;
use app\libraries\response\MultiResponse;
use app\libraries\response\WebResponse;
use app\libraries\response\JsonResponse;
use app\libraries\response\RedirectResponse;
use app\models\Notification;
use Symfony\Component\Routing\Annotation\Route;

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
        'reply_in_post_thread',
        'team_invite',
        'team_joined',
        'team_member_submission',
        'self_notification'
    ];

    const EMAIL_SELECTIONS = [
        'merge_threads_email',
        'all_new_threads_email',
        'all_new_posts_email',
        'all_modifications_forum_email',
        'reply_in_post_thread_email',
        'team_invite_email',
        'team_joined_email',
        'team_member_submission_email',
        'self_notification_email'
    ];

    protected $selections;

    public function __construct(Core $core) {
        parent::__construct($core);
        $this->selections = self::NOTIFICATION_SELECTIONS;
        if ($this->core->getConfig()->isEmailEnabled()) {
            $this->selections = array_merge($this->selections, self::EMAIL_SELECTIONS);
        }
    }
    /**
     * @param $show_all
     * @Route("/{_semester}/{_course}/notifications")
     * @return MultiResponse
     */
    public function showNotifications($show_all = null) {
        $show_all = isset($show_all) ? true : false;
        $notifications = $this->core->getQueries()->getUserNotifications($this->core->getUser()->getId(), $show_all);
        return MultiResponse::webOnlyResponse(
            new WebResponse(
                'Notification',
                'showNotifications',
                $this->core->getConfig()->getCourse(),
                $show_all,
                $notifications,
                $this->core->getUser()->getNotificationSettings()
            )
        );
    }

    /**
     * @param $nid
     * @param $seen
     * @Route("/{_semester}/{_course}/notifications/{nid}", requirements={"nid": "[1-9]\d*"})
     * @return MultiResponse
     */
    public function openNotification($nid, $seen) {
        $user_id = $this->core->getUser()->getId();
        $metadata = $this->core->getQueries()->getNotificationInfoById($user_id, $nid)['metadata'];
        if (!$seen) {
            $thread_id = Notification::getThreadIdIfExists($metadata);
            $this->core->getQueries()->markNotificationAsSeen($user_id, $nid, $thread_id);
        }
        return MultiResponse::RedirectOnlyResponse(
            new RedirectResponse(Notification::getUrl($this->core, $metadata))
        );
    }

    /**
     * @param $nid
     * @Route("/{_semester}/{_course}/notifications/{nid}/seen", requirements={"nid": "[1-9]\d*"})
     * @return MultiResponse
     */
    public function markNotificationAsSeen($nid) {
        $this->core->getQueries()->markNotificationAsSeen($this->core->getUser()->getId(), $nid);
        return MultiResponse::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['notifications']))
        );
    }

    /**
     * @Route("/{_semester}/{_course}/notifications/seen")
     * @return MultiResponse
     */
    public function markAllNotificationsAsSeen() {
        $this->core->getQueries()->markNotificationAsSeen($this->core->getUser()->getId(), -1);
        return MultiResponse::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['notifications']))
        );
    }

    /**
     * @Route("/{_semester}/{_course}/notifications/settings", methods={"GET"})
     * @return MultiResponse
     */
    public function viewNotificationSettings() {
        return MultiResponse::webOnlyResponse(
            new WebResponse(
                'Notification',
                'showNotificationSettings',
                $this->core->getUser()->getNotificationSettings()
            )
        );
    }

    /**
     * @Route("/{_semester}/{_course}/notifications/settings", methods={"POST"})
     * @return MultiResponse
     */
    public function changeSettings() {
        //Change settings for the current user.
        unset($_POST['csrf_token']);
        $new_settings = $_POST;

        if ($this->validateNotificationSettings(array_keys($new_settings))) {
            $values_not_sent = array_diff($this->selections, array_keys($new_settings));
            foreach (array_values($values_not_sent) as $value) {
                $new_settings[$value] = 'false';
            }
            $this->core->getQueries()->updateNotificationSettings($new_settings);
            return MultiResponse::JsonOnlyResponse(
                JsonResponse::getSuccessResponse('Notification settings have been saved.')
            );
        }
        else {
            return MultiResponse::JsonOnlyResponse(
                JsonResponse::getFailResponse('Notification settings could not be saved. Please try again.')
            );
        }
    }

    private function validateNotificationSettings($columns) {
        if (count($columns) <= count($this->selections) && count(array_intersect($columns, $this->selections)) == count($columns)) {
            return true;
        }
        return false;
    }
}
