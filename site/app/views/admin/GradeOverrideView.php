<?php

namespace app\views\admin;

use app\views\AbstractView;
use app\libraries\Utils;

class GradeOverrideView extends AbstractView {
    public function displayOverridenGrades($gradeables) {

        $students = $this->core->getQueries()->getAllUsers();
        $student_full = Utils::getAutoFillData($students);

        return $this->core->getOutput()->renderTwigTemplate("admin/GradeOverride.twig",[
            "gradeables" => $gradeables,
            "student_full" => $student_full,
            "csrf_token" => $this->core->getCsrfToken()
        ] );
    }
}