<?php

namespace app\views;

class NotificationView extends AbstractView {
    public function showNotifications($current_course, $show_all, $notifications, $notification_saves) {
        $this->core->getOutput()->addBreadcrumb("Notifications");
        $this->core->getOutput()->renderTwigOutput("Notifications.twig", [
            'course' => $current_course,
            'show_all' => $show_all,
            'notifications' => $notifications,
            'notification_saves' => $notification_saves,
            'notifications_url' => $this->core->buildNewCourseUrl(['notifications']),
            'mark_all_as_seen_url' => $this->core->buildNewCourseUrl(['notifications', 'seen']),
            'notification_settings_url' => $this->core->buildNewCourseUrl(['notifications', 'settings'])
        ]);
    }

    public function showNotificationSettings($notification_saves) {
        $this->core->getOutput()->addBreadcrumb("Notifications", $this->core->buildNewCourseUrl(['notifications']));
        $this->core->getOutput()->addBreadcrumb("Notification Settings");
        $this->core->getOutput()->renderTwigOutput("NotificationSettings.twig", [
            'notification_saves' => $notification_saves,
            'email_enabled' => $this->core->getConfig()->isEmailEnabled(),
            'csrf_token' => $this->core->getCsrfToken(),
            'update_settings_url' => $this->core->buildNewCourseUrl(['notifications', 'settings'])
        ]);
    }
}
