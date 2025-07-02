<?php

namespace app\views\admin;

use app\views\AbstractView;
use app\libraries\Utils;

class GradeOverrideView extends AbstractView {
    public function displayOverriddenGrades(array $gradeables, array $students) {
        $this->core->getOutput()->addInternalCss('table.css');
        $this->core->getOutput()->addBreadcrumb('Grades Override');
        $this->core->getOutput()->addInternalJs('grade-override.js');

        $student_full = Utils::getAutoFillData($students);
        $this->core->getOutput()->enableMobileViewport();

        return $this->core->getOutput()->renderTwigTemplate("admin/GradeOverride.twig", [
            "gradeables" => $gradeables,
            "student_full" => $student_full,
            "csrf_token" => $this->core->getCsrfToken()
        ]);
    }
}
