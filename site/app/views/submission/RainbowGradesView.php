<?php

namespace app\views\submission;

use app\views\AbstractView;
use app\models\User;

class RainbowGradesView extends AbstractView {
    /**
     * Renders the Rainbow Grades page.
     *
     * @param string|null $grade_file The contents of the grade file, or null if not available.
     */
    public function showGrades($grade_file): string {
        $this->core->getOutput()->addBreadcrumb('Rainbow Grades');
        $this->core->getOutput()->addInternalCss('rainbow-grades.css');
        return $this->core->getOutput()->renderTwigTemplate("submission/RainbowGrades.twig", [
            "show_summary" => $grade_file !== null,
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

        return $this->core->getOutput()->renderTwigTemplate("submission/RainbowGrades.twig", [
            "show_summary" => $grade_file !== null,
            "grade_file" => $grade_file,
            "extra_label" => "For " . $user->getDisplayFullName()
        ]);
    }
}
