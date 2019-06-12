<?php

namespace app\views;

class NotificationView extends AbstractView {
    public function showNotifications($current_course, $show_all, $notifications, $notification_saves) {
        $this->core->getOutput()->addBreadcrumb("Notifications");
        $this->core->getOutput()->renderTwigOutput("Notifications.twig", [
            'course' => $current_course,
            'show_all' => $show_all,
            'notifications' => $notifications,
            'notification_saves' => $notification_saves
        ]);
    }

    public function showNotificationSettings($notification_saves) {
        $this->core->getOutput()->addBreadcrumb("Notifications", $this->core->buildUrl(['component' => 'notification', 'page' => 'notifications']));
        $this->core->getOutput()->addBreadcrumb("Notification Settings");
        $this->core->getOutput()->renderTwigOutput("NotificationSettings.twig", [
            'notification_saves' => $notification_saves
        ]);
    }
}