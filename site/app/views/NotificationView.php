<?php

namespace app\views;

use app\models\User;

class NotificationView extends AbstractView {
    public function showNotifications($current_course, $show_all, $notifications, $notification_saves) {
        $this->core->getOutput()->addBreadcrumb("Notifications");
        $this->core->getOutput()->addInternalCss('notifications.css');
        $this->core->getOutput()->enableMobileViewport();
        $this->core->getOutput()->renderTwigOutput("Notifications.twig", [
            'course' => $current_course,
            'show_all' => $show_all,
            'notifications' => $notifications,
            'notification_saves' => $notification_saves,
            'notifications_url' => $this->core->buildCourseUrl(['notifications']),
            'mark_all_as_seen_url' => $this->core->buildCourseUrl(['notifications', 'seen']),
            'notification_settings_url' => $this->core->buildCourseUrl(['notifications', 'settings'])
        ]);
    }

    public function showNotificationSettings($notification_saves) {
        $this->core->getOutput()->addBreadcrumb("Notifications", $this->core->buildCourseUrl(['notifications']));
        $this->core->getOutput()->addInternalCss('notifications.css');
        $this->core->getOutput()->addBreadcrumb("Notification Settings");
        $this->core->getOutput()->renderTwigOutput("NotificationSettings.twig", [
            'notification_saves' => $notification_saves,
            'email_enabled' => $this->core->getConfig()->isEmailEnabled(),
            'csrf_token' => $this->core->getCsrfToken(),
            'defaults' => User::constructNotificationSettings([]),
            'update_settings_url' => $this->core->buildCourseUrl(['notifications', 'settings']),
            'self_registration_type' => $this->core->getQueries()->getSelfRegistrationType($this->core->getConfig()->getTerm(), $this->core->getConfig()->getCourse())
        ]);
    }
}
