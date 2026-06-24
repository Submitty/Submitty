<?php

namespace app\views;

use app\controllers\admin\ConfigurationController;
use app\models\Notification;
use app\models\User;
use app\models\Course;

class NotificationView extends AbstractView {
    /**
     * @param string $current_course
     * @param Notification[] $all_notifications
     * @param array<string, mixed> $notification_saves
     * @return string
     */
    public function showNotifications(string $current_course, array $all_notifications, array $notification_saves): string {
        $this->core->getOutput()->addBreadcrumb("Notifications");
        $this->core->getOutput()->addInternalCss('notifications.css');
        $this->core->getOutput()->enableMobileViewport();
        return $this->core->getOutput()->renderTwigTemplate("Vue.twig", [
            "type" => "page",
            "name" => "CourseNotificationsPage",
            "args" => [
                "notifications" => $all_notifications
            ]
        ]);
    }

    /**
     * @param array<string, bool> $notification_saves
     * @param int $self_registration_type
     * @param array <int, Course> $courses
     * @param bool $is_default_course
     * @param array<string, string>|null $default_course
     * @return void
     */
    public function showNotificationSettings(
        array $notification_saves,
        int $self_registration_type,
        array $courses = [],
        bool $is_default_course = false,
        ?array $default_course = null
    ): void {
        $this->core->getOutput()->renderTwigOutput("NotificationSettings.twig", [
        'notification_saves'      => $notification_saves,
        'email_enabled'           => $this->core->getConfig()->isEmailEnabled(),
        'csrf_token'              => $this->core->getCsrfToken(),
        'defaults'                => User::constructNotificationSettings([]),
        'update_settings_url'     => $this->core->buildCourseUrl(['notifications', 'settings']),
        'save_defaults_url'  => $this->core->buildCourseUrl(['notifications', 'save_defaults']),
        'clear_defaults_url' => $this->core->buildCourseUrl(['notifications', 'clear_defaults']),
        'is_default_course'   => $is_default_course,
        'default_course'      => $default_course,
        'self_registration_type'  => $self_registration_type,
        'is_instructor'           => $this->core->getUser()->accessAdmin(),
        'is_self_registration'    => $self_registration_type !== ConfigurationController::NO_SELF_REGISTER,
        'courses'                 => $courses,
        ]);
    }
}
