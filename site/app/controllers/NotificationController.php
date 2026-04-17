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
        'self_notification',
        'all_released_grades',
        'all_gradeable_releases'
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
        'self_notification_email',
        'self_registration_email',
        'all_released_grades_email',
        'all_gradeable_releases_email'
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
     * @return MultiResponse
     */
    #[Route("/courses/{_semester}/{_course}/notifications")]
    public function showNotifications() {
        $all_notifications = $this->core->getQueries()->getUserNotifications($this->core->getUser()->getId(), true);
        return MultiResponse::webOnlyResponse(
            new WebResponse(
                'Notification',
                'showNotifications',
                $this->core->getConfig()->getCourse(),
                $all_notifications,
                $this->core->getUser()->getNotificationSettings()
            )
        );
    }

    /**
     * @param string $nid
     * @param string|null $seen
     *
     * @return MultiResponse
     */
    #[Route("/courses/{_semester}/{_course}/notifications/{nid}", requirements: ["nid" => "[1-9]\d*"])]
    public function openNotification($nid, $seen) {
        $user_id = $this->core->getUser()->getId();
        $metadata = $this->core->getQueries()->getNotificationInfoById($user_id, $nid)['metadata'];
        if (!$seen) {
            $thread_id = Notification::getThreadIdIfExists($metadata);
            $this->core->getQueries()->markNotificationAsSeen($user_id, intval($nid), $thread_id);
        }
        return MultiResponse::RedirectOnlyResponse(
            new RedirectResponse(Notification::getUrl($this->core, $metadata))
        );
    }

    /**
     * @return JsonResponse
     */
    #[Route("/courses/{_semester}/{_course}/notifications/mark_seen", methods: ["POST"])]
    public function markNotificationAsSeen(): JsonResponse {
        $nid = intval($_POST['notification_id'] ?? 0);
        $this->core->getQueries()->markNotificationAsSeen($this->core->getUser()->getId(), $nid);
        return JsonResponse::getSuccessResponse(['notification_id' => $nid]);
    }

    /**
     * @return JsonResponse
     */
    #[Route("/courses/{_semester}/{_course}/notifications/seen")]
    public function markAllNotificationsAsSeen() {
        $this->core->getQueries()->markNotificationAsSeen($this->core->getUser()->getId(), -1);
        return JsonResponse::getSuccessResponse(['success' => true]);
    }

    /**
     * @return MultiResponse
     */
    #[Route("/courses/{_semester}/{_course}/notifications/settings", methods: ["GET"])]
    public function viewNotificationSettings() {
        $original_config = clone $this->core->getConfig();
        $this->core->loadMasterConfig();
        $this->core->loadMasterDatabase();
        $courses = $this->core->getQueries()->getCourseForUserId($this->core->getUser()->getId());
        $this->core->setConfig($original_config);
        $this->core->loadCourseDatabase();

        return MultiResponse::webOnlyResponse(
            new WebResponse(
                'Notification',
                'showNotificationSettings',
                $this->core->getUser()->getNotificationSettings(),
                $this->core->getQueries()->getSelfRegistrationType($this->core->getConfig()->getTerm(), $this->core->getConfig()->getCourse()), 
                $courses
            )
        );
    }
    /**
     * @return MultiResponse
     */
    #[Route("/courses/{_semester}/{_course}/notifications/sync", methods: ["POST"])]
    public function syncNotifications() {
        $course_ids = $_POST['sync_course_ids'] ?? [];
        unset($_POST['csrf_token'], $_POST['sync_course_ids']);
        $new_settings = $_POST;
        if (empty($course_ids)) {
            return MultiResponse::JsonOnlyResponse(
                JsonResponse::getFailResponse("No courses selected.")
            );
        }
        foreach ($course_ids as $course_id) {
            $parts = explode('|', $course_id);
            if (count($parts) !== 2) {
                continue; 
            }
            [$semester, $course_name] = $parts;
            $this->core->loadCourseConfig($semester, $course_name);
            $this->core->loadCourseDatabase();
            if (!$this->changeSettings($new_settings)) {
                return MultiResponse::JsonOnlyResponse(
                    JsonResponse::getFailResponse(
                        "Failed to sync settings for {$semester} {$course_name}."
                    )
                );
            }
        }
        return MultiResponse::JsonOnlyResponse(
            JsonResponse::getSuccessResponse(
                "Notification settings have been synced successfully."
            )
        );
    }
    /**
     * @return MultiResponse
     */
    #[Route("/courses/{_semester}/{_course}/notifications/settings", methods: ["POST"])]
    public function changeCourseNotificationSettings() {
        //Change settings for the current user.
        unset($_POST['csrf_token']);
        $new_settings = $_POST;
        if ($this->changeSettings($new_settings)) {
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
    private function changeSettings($new_settings) {
        if ($this->validateNotificationSettings(array_keys($new_settings))) {
            $values_not_sent = array_diff($this->selections, array_keys($new_settings));
            foreach (array_values($values_not_sent) as $value) {
                $new_settings[$value] = 'false';
            }
            $this->core->getQueries()->updateNotificationSettings($new_settings);
            return true;
        }
        return false;
    }

    private function validateNotificationSettings($columns) {
        if (count($columns) <= count($this->selections) && count(array_intersect($columns, $this->selections)) == count($columns)) {
            return true;
        }
        return false;
    }
}
