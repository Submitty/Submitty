<?php

namespace app\views;

use app\controllers\admin\ConfigurationController;
use app\models\User;

class NotificationView extends AbstractView {
    public function showNotifications($current_course, $all_notifications, $notification_saves) {
        $this->core->getOutput()->addBreadcrumb("Notifications");
        $this->core->getOutput()->addInternalCss('notifications.css');
        $this->core->getOutput()->enableMobileViewport();
        return $this->core->getOutput()->renderTwigTemplate("Vue.twig", [
            "type" => "page",
            "name" => "CourseNotificationsPage",
            "args" => [
                "notifications" => $all_notifications,
                "notifications_url" => $this->core->buildCourseUrl(['notifications']),
            ]
        ]);
    }

    public function showNotificationSettings($notification_saves, int $self_registration_type) {
        $this->core->getOutput()->addBreadcrumb("Notifications", $this->core->buildCourseUrl(['notifications']));
        $this->core->getOutput()->addInternalCss('notifications.css');
        $this->core->getOutput()->addBreadcrumb("Notification Settings");
        $this->core->getOutput()->renderTwigOutput("NotificationSettings.twig", [
            'notification_saves' => $notification_saves,
            'email_enabled' => $this->core->getConfig()->isEmailEnabled(),
            'csrf_token' => $this->core->getCsrfToken(),
            'defaults' => User::constructNotificationSettings([]),
            'update_settings_url' => $this->core->buildCourseUrl(['notifications', 'settings']),
            'self_registration_type' => $self_registration_type,
            'is_instructor' => $this->core->getUser()->accessAdmin(),
            'is_self_registration' => $self_registration_type !== ConfigurationController::NO_SELF_REGISTER
        ]);
    }
}
