<?php

namespace app\views\admin;

use app\views\AbstractView;
use app\libraries\Utils;

class ExtensionsView extends AbstractView {
    public function displayExtensions($gradeables) {
        $students = $this->core->getQueries()->getAllUsers();
        $student_full = Utils::getAutoFillData($students);

        return $this->core->getOutput()->renderTwigTemplate("admin/Extensions.twig", [
            "gradeables" => $gradeables,
            "student_full" => $student_full,
            "csrf_token" => $this->core->getCsrfToken()
        ]);
    }
}
