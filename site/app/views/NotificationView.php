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
     * @param bool $has_defaults
     * @return void
     */
    public function showNotificationSettings(
        array $notification_saves,
        int $self_registration_type,
        array $courses = [],
        bool $is_default_course = false
    ): void {
        $this->core->getOutput()->renderTwigOutput("NotificationSettings.twig", [
            'save_defaults_url'  => $this->core->buildCourseUrl(['notifications', 'save_defaults']),
            'is_default_course'  => $is_default_course,
        ]);
    }
}
