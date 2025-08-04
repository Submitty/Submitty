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
        'all_released_grades'
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
        'all_released_grades_email'
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
     * @param string|null $show_all
     * @return MultiResponse
     */
    #[Route("/courses/{_semester}/{_course}/notifications")]
    public function showNotifications(?string $show_all = null) {
        $show_all = !empty($show_all);
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
     * @param string $nid
     *
     * @return MultiResponse
     */
    #[Route("/courses/{_semester}/{_course}/notifications/{nid}/seen", requirements: ["nid" => "[1-9]\d*"])]
    public function markNotificationAsSeen($nid) {
        $this->core->getQueries()->markNotificationAsSeen($this->core->getUser()->getId(), intval($nid));
        return MultiResponse::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['notifications']))
        );
    }

    /**
     * @return MultiResponse
     */
    #[Route("/courses/{_semester}/{_course}/notifications/seen")]
    public function markAllNotificationsAsSeen() {
        $this->core->getQueries()->markNotificationAsSeen($this->core->getUser()->getId(), -1);
        return MultiResponse::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildCourseUrl(['notifications']))
        );
    }

    /**
     * @return MultiResponse
     */
    #[Route("/courses/{_semester}/{_course}/notifications/settings", methods: ["GET"])]
    public function viewNotificationSettings() {
        return MultiResponse::webOnlyResponse(
            new WebResponse(
                'Notification',
                'showNotificationSettings',
                $this->core->getUser()->getNotificationSettings(),
                $this->core->getQueries()->getSelfRegistrationType($this->core->getConfig()->getTerm(), $this->core->getConfig()->getCourse())
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

            // Auto-sync to other courses if user has sync enabled
            $this->autoSyncIfEnabled($new_settings);

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

    /**
     * @return JsonResponse
     */
    #[Route("/courses/{_semester}/{_course}/notifications/settings/sync", methods: ["POST"])]
    public function updateNotificationSync() {
        $user_id = $this->core->getUser()->getId();
        $synced = isset($_POST['notifications_synced']) && $_POST['notifications_synced'] === 'true';
        if ($synced) {
            // Get current course's notification settings
            $current_settings = $this->core->getUser()->getNotificationSettings();
            $sync_settings = [];
            foreach ($this->selections as $setting) {
                if (isset($current_settings[$setting])) {
                    $sync_settings[$setting] = $current_settings[$setting];
                }
            }

            // Fetch all active courses for the user
            $courses = $this->core->getQueries()->getCourseForUserId($user_id);

            if (count($courses) === 0) {
                return JsonResponse::getFailResponse('You need to be enrolled in active courses to sync notification settings.');
            }

            // Sync settings to all active courses
            foreach ($courses as $course) {
                $term = $course->getTerm();
                $course_name = $course->getTitle();

                // Skip the current course as updates have already been applied
                if ($term === $this->core->getConfig()->getTerm() && $course_name === $this->core->getConfig()->getCourse()) {
                    continue;
                }

                $this->core->getQueries()->syncNotificationSettingsToCourse(
                    $user_id,
                    $sync_settings,
                    $term,
                    $course_name
                );
            }
        }

        // Update sync status
        $timestamp = $this->core->getDateTimeNow()->format('Y-m-d H:i:s');
        $this->core->getQueries()->updateNotificationSync($user_id, $synced, $timestamp);
        $this->core->getUser()->setNotificationsSynced($synced);
        $this->core->getUser()->setNotificationsSyncedUpdate($timestamp);
        $action = $synced ? 'enabled' : 'disabled';
        return JsonResponse::getSuccessResponse("Notification sync has been {$action}.");
    }

    /**
     * @return MultiResponse
     */
    #[Route("/courses/{_semester}/{_course}/notifications/settings/defaults", methods: ["POST"])]
    public function updateNotificationDefaults() {
        $user_id = $this->core->getUser()->getId();
        $defaults = $_POST['notification_defaults'] ?? null;
        if ($defaults === 'null' || $defaults === '') {
            $defaults = null;
            $message = 'Default notification settings have been cleared.';
        }
        else {
            $current_term = $this->core->getConfig()->getTerm();
            $current_course = $this->core->getConfig()->getCourse();
            $defaults = $current_term . '-' . $current_course;
            $message = 'These notification settings have been saved as your default for future courses.';
        }
        $this->core->getQueries()->updateNotificationDefaults($user_id, $defaults);
        $this->core->getUser()->setNotificationDefaults($defaults);
        return JsonResponse::getSuccessResponse($message);
    }

    /**
     * Automatically sync notification settings to other courses if user has sync enabled
     * @param array<string, bool> $new_settings The updated notification settings
     * @return void
     */
    private function autoSyncIfEnabled(array $new_settings): void {
        $user = $this->core->getUser();

        // Only sync if user has sync enabled
        if (!$user->getNotificationsSynced()) {
            return;
        }

        $user_id = $user->getId();
        $courses = $this->core->getQueries()->getCourseForUserId($user_id);

        // Filter settings to only include valid notification options
        $sync_settings = [];
        foreach ($this->selections as $setting) {
            if (isset($new_settings[$setting])) {
                $sync_settings[$setting] = $new_settings[$setting];
            }
        }

        // Sync to all other active courses
        foreach ($courses as $course) {
            $term = $course->getTerm();
            $course_name = $course->getTitle();

            // Skip the current course as it's already been updated
            if ($term === $this->core->getConfig()->getTerm() && $course_name === $this->core->getConfig()->getCourse()) {
                continue;
            }

            $this->core->getQueries()->syncNotificationSettingsToCourse(
                $user_id,
                $sync_settings,
                $term,
                $course_name
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
