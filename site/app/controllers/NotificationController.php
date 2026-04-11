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
        $unarchived_courses = $this->core->getQueries()->getUnarchivedCoursesById($this->core->getUser()->getId());
        $current_term = $this->core->getConfig()->getTerm();
        $current_course = $this->core->getConfig()->getCourse();
        $unarchived_courses = array_filter($unarchived_courses, function ($c) use ($current_term, $current_course) {
            return $c['term'] !== $current_term || $c['course'] !== $current_course;
        });

        return MultiResponse::webOnlyResponse(
            new WebResponse(
                'Notification',
                'showNotificationSettings',
                $this->core->getUser()->getNotificationSettings(),
                $this->core->getQueries()->getSelfRegistrationType($this->core->getConfig()->getTerm(), $this->core->getConfig()->getCourse()),
                $unarchived_courses
            )
        );
    }

    /**
     * @return MultiResponse
     */
    #[Route("/courses/{_semester}/{_course}/notifications/settings", methods: ["POST"])]
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

    #[Route("/courses/{_semester}/{_course}/notifications/settings/sync", methods: ["POST"])]
    public function syncSettings() {
        $target_courses = $_POST['target_courses'] ?? [];
        if (empty($target_courses)) {
            return MultiResponse::JsonOnlyResponse(JsonResponse::getFailResponse('No courses selected for sync.'));
        }

        unset($_POST['csrf_token']);
        unset($_POST['target_courses']);
        $new_settings = $_POST;

        if ($this->validateNotificationSettings(array_keys($new_settings))) {
            $values_not_sent = array_diff($this->selections, array_keys($new_settings));
            foreach (array_values($values_not_sent) as $value) {
                $new_settings[$value] = 'false';
            }

            $success_count = 0;
            $fail_count = 0;
            foreach ($target_courses as $course_id) {
                $parts = explode(':', $course_id);
                if (count($parts) !== 2) {
                    continue;
                }
                [$term, $course] = $parts;
                if ($this->core->getQueries()->syncNotificationSettings($this->core->getUser()->getId(), $term, $course, $new_settings)) {
                    $success_count++;
                }
                else {
                    $fail_count++;
                }
            }

            if ($fail_count === 0) {
                return MultiResponse::JsonOnlyResponse(JsonResponse::getSuccessResponse("Notification settings synced to $success_count courses."));
            }
            else {
                return MultiResponse::JsonOnlyResponse(JsonResponse::getSuccessResponse("Notification settings synced to $success_count courses ($fail_count failed)."));
            }
        }
        return MultiResponse::JsonOnlyResponse(JsonResponse::getFailResponse('Invalid settings.'));
    }

    #[Route("/courses/{_semester}/{_course}/notifications/settings/default", methods: ["POST"])]
    public function saveDefaultSettings() {
        unset($_POST['csrf_token']);
        $new_settings = $_POST;

        if ($this->validateNotificationSettings(array_keys($new_settings))) {
            $values_not_sent = array_diff($this->selections, array_keys($new_settings));
            foreach (array_values($values_not_sent) as $value) {
                $new_settings[$value] = 'false';
            }
            $this->core->getQueries()->updateDefaultNotificationSettings($new_settings);
            return MultiResponse::JsonOnlyResponse(JsonResponse::getSuccessResponse('Default notification settings have been saved.'));
        }
        return MultiResponse::JsonOnlyResponse(JsonResponse::getFailResponse('Invalid settings.'));
    }

    private function validateNotificationSettings($columns) {
        if (count($columns) <= count($this->selections) && count(array_intersect($columns, $this->selections)) == count($columns)) {
            return true;
        }
        return false;
    }
}
