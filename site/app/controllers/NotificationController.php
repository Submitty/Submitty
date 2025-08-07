<?php

namespace app\controllers;

use app\libraries\Core;
use app\libraries\response\MultiResponse;
use app\libraries\response\WebResponse;
use app\libraries\response\JsonResponse;
use app\libraries\response\RedirectResponse;
use app\models\Notification;
use app\models\User;
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

            // Auto-sync new settings to other active courses, which only applies if user has syncing enabled
            $this->autoSyncNotificationSettings($new_settings);

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
    #[Route("/notifications/settings/sync", methods: ["POST"])]
    #[Route("/courses/{_semester}/{_course}/notifications/settings/sync", methods: ["POST"])]
    public function updateNotificationSync() {
        $user_id = $this->core->getUser()->getId();
        $syncing = isset($_POST['notifications_synced']) && $_POST['notifications_synced'] === 'true';

        if ($syncing) {
            $sync_settings = [];
            $notification_settings = $this->core->getUser()->getNotificationSettings();

            foreach ($this->selections as $setting) {
                // Only persist the valid settings that are set, otherwise default them to false
                if (isset($notification_settings[$setting])) {
                    $sync_settings[$setting] = $notification_settings[$setting];
                }
                else {
                    $sync_settings[$setting] = false;
                }
            }

            $error_message = $this->autoSyncNotificationSettings($sync_settings, true);

            if ($error_message !== null) {
                // Implies no active courses to sync to
                return JsonResponse::getFailResponse($error_message);
            }
        }

        $action = $syncing ? 'enabled' : 'disabled';
        $this->core->getQueries()->updateNotificationSync($user_id, $syncing);
        $this->core->getUser()->setNotificationsSynced($syncing);
        return JsonResponse::getSuccessResponse('Notification syncing has been ' . ($action));
    }

    /**
     * @return JsonResponse
     */
    #[Route("/notifications/settings/defaults/view", methods: ["POST"])]
    public function getNotificationDefaults() {
        $user_id = $this->core->getUser()->getId();
        $defaults_string = $_POST['course_key'] ?? null;

        if ($defaults_string === null) {
            return JsonResponse::getFailResponse('Invalid course key.');
        }

        // Parse the reference course from the defaults string (term-course)
        $parts = explode('-', $defaults_string, 2);
        if (count($parts) !== 2) {
            return JsonResponse::getFailResponse('Invalid default notification settings format.');
        }

        $target_term = $parts[0];
        $target_course = $parts[1];

        // Store the original configuration
        $original_config = clone $this->core->getConfig();

        try {
            // Connect to target course database
            $this->core->loadCourseConfig($target_term, $target_course);
            $this->core->loadCourseDatabase();

            // Get the notification settings from the target course or fallback to default settings
            $settings = $this->core->getQueries()->getNotificationSettings($user_id);
            $missing_settings = $settings === null;
            // Normalize the settings to include mandatory settings not found in the database
            $settings = User::constructNotificationSettings($settings ?? []);

            // Remove user_id from the settings array and format for display
            $formatted_settings = [];
            unset($settings['user_id']);

            foreach ($settings as $key => $value) {
                $formatted_settings[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
            }

            return JsonResponse::getSuccessResponse([
                'course' => $target_term . ' - ' . $target_course,
                'settings' => $formatted_settings,
                'missing_settings' => $missing_settings
            ]);
        }
        finally {
            // Restore original configuration
            $this->core->setConfig($original_config);
            $this->core->loadCourseDatabase();
        }
    }

    /**
     * @return JsonResponse
     */
    #[Route("/notifications/settings/defaults", methods: ["POST"])]
    #[Route("/courses/{_semester}/{_course}/notifications/settings/defaults", methods: ["POST"])]
    public function updateNotificationDefaults() {
        $user_id = $this->core->getUser()->getId();
        $defaults = $_POST['notification_defaults'] ?? null;

        if ($defaults === 'null' || $defaults === '') {
            $defaults = null;
            $message = 'Default notification settings have been cleared.';
        }
        else {
            $defaults = $_POST['course_key'] ?? $this->core->getConfig()->getTerm() . '-' . $this->core->getConfig()->getCourse();
            $message = 'Default notification settings have been set for future courses.';
        }

        $this->core->getQueries()->updateNotificationDefaults($user_id, $defaults);
        $this->core->getUser()->setNotificationDefaults($defaults);
        return JsonResponse::getSuccessResponse($message);
    }

    /**
     * Automatically sync notification settings to other courses if the user has sync enabled or is enabling sync.
     *
     * @param array<string, bool> $sync_settings The updated notification settings
     * @param bool $enabling Whether the user is enabling sync
     * @return string|null Null if sync is not applied or has been applied successfully, otherwise a message indicating why sync failed
     */
    private function autoSyncNotificationSettings(array $sync_settings, ?bool $enabling = false): ?string {
        $user = $this->core->getUser();
        $synced = $user->getNotificationsSynced();

        // Only sync if user has sync enabled or is enabling sync
        if (!$synced && !$enabling) {
            return null;
        }

        // Fetch all active courses for the user
        $user_id = $user->getId();
        $courses = $this->core->getQueries()->getCourseForUserId($user_id);

        if ($synced || $enabling) {
            // Get all active courses for the user
            $courses = $this->core->getQueries()->getCourseForUserId($user_id);

            if (count($courses) === 0) {
                return 'You need to be enrolled in active courses to sync notification settings.';
            }

            // Store the core application configuration before connecting to other course databases
            $original_config = clone $this->core->getConfig();

            // Sync the notification settings to all active courses
            foreach ($courses as $course) {
                $term = $course->getTerm();
                $course_name = $course->getTitle();

                // Skip the current course as updates are implicitly applied to it
                if ($term === $this->core->getConfig()->getTerm() && $course_name === $this->core->getConfig()->getCourse()) {
                    continue;
                }

                // Connect to the target course database
                $this->core->loadCourseConfig($term, $course_name);
                $this->core->loadCourseDatabase();
                $course_db = $this->core->getCourseDB();

                // Update the notification settings for the user in the target course database
                $this->core->getQueries()->updateNotificationSettings($sync_settings);

                // Close the connection to the course database after syncing
                $course_db->disconnect();
            }

            // Restore the original core application configuration after syncing
            $this->core->setConfig($original_config);
            $this->core->loadCourseDatabase();
        }

        return null;
    }

    private function validateNotificationSettings($columns) {
        if (count($columns) <= count($this->selections) && count(array_intersect($columns, $this->selections)) == count($columns)) {
            return true;
        }
        return false;
    }
}
