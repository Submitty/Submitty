<?php

namespace app\views\submission;

use app\views\AbstractView;
use app\models\User;

class RainbowGradesView extends AbstractView {
    /**
     * Renders the Rainbow Grades page.
     * @param string|null $grade_file The contents of the grade file, or null if not available.
     */
    public function showGrades($grade_file): string {
        $this->core->getOutput()->addBreadcrumb('Rainbow Grades');
        $this->core->getOutput()->addInternalCss('rainbow-grades.css');

        $rainbow_grade_active = $this->core->getConfig()->displayRainbowGradesSummary();
        $config_url = $this->core->buildCourseUrl(['config#display-rainbow-grades-summary']);
        return $this->core->getOutput()->renderTwigTemplate("submission/RainbowGrades.twig", [
            "show_summary" => $grade_file !== null,
            "rainbow_grade_active" => $rainbow_grade_active,
            "config_url" => $config_url,
            "grade_file" => $grade_file
        ]);
    }

    /**
     * Renders the Rainbow Grades page for a specific student.
     *
     * @param User $user The user whose grades are being displayed.
     * @param string|null $grade_file The contents of the grade file for the student, or null if not available.
     */
    public function showStudentToInstructor($user, $grade_file): string {
        $manage_url = $this->core->buildCourseUrl(['users']);
        $this->core->getOutput()->addBreadcrumb('Manage Students', $manage_url);
        $this->core->getOutput()->addBreadcrumb($user->getDisplayFullName());
        $this->core->getOutput()->addInternalCss('rainbow-grades.css');

        $rainbow_grade_active = $this->core->getConfig()->displayRainbowGradesSummary();
        $config_url = $this->core->buildCourseUrl(['config#display-rainbow-grades-summary']);

        return $this->core->getOutput()->renderTwigTemplate("submission/RainbowGrades.twig", [
            "show_summary" => $grade_file !== null,
            "grade_file" => $grade_file,
            "rainbow_grade_active" => $rainbow_grade_active,
            "config_url" => $config_url,
            "extra_label" => "For " . $user->getDisplayFullName()
        ]);
    }
}
