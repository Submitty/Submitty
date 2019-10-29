<?php

namespace app\views\admin;

use app\views\AbstractView;
class EmailRoomSeatingView extends AbstractView {
    public function displayPage($defaultSubject, $defaultBody) {
        $this->core->getOutput()->addBreadcrumb("Email Room Seating");
        return $this->core->getOutput()->renderTwigTemplate("admin/EmailRoomSeating.twig", [
            "defaultSubject" => $defaultSubject,
            "defaultBody" => $defaultBody,
            "email_enabled" => $this->core->getConfig()->isEmailEnabled(),
            "csrf_token" => $this->core->getCsrfToken(),
            "send_email_url" => $this->core->buildCourseUrl(['email_room_seating', 'send'])
        ]);
    }
}
