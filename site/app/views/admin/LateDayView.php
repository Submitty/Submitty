<?php

namespace app\views\admin;

use app\views\AbstractView;
use app\libraries\Utils;

class LateDayView extends AbstractView {
    public function displayLateDays($users) {
        $this->core->getOutput()->addInternalCss('latedays.css');
        $this->core->getOutput()->addBreadcrumb('Late Days Allowed');

        $students = $this->core->getQueries()->getAllUsers();
        $student_full = Utils::getAutoFillData($students);

        return $this->core->getOutput()->renderTwigTemplate("admin/LateDays.twig", [
            "users" => $users,
            "student_full" => $student_full,
            "csrf_token" => $this->core->getCsrfToken()
        ]);
    }
}