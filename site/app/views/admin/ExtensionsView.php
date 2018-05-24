<?php

namespace app\views\admin;

use app\views\AbstractView;

class ExtensionsView extends AbstractView {
    public function displayExtensions($gradeables) {
        $students = $this->core->getQueries()->getAllUsers();
        $student_full = array();
        foreach ($students as $student) {
            $student_full[] = array('value' => $student->getId(),
                                    'label' => $student->getDisplayedFirstName().' '.$student->getLastName().' <'.$student->getId().'>');
        }
        $student_full = json_encode($student_full);

        return $this->core->getOutput()->renderTwigTemplate("admin/Extensions.twig", [
            "gradeables" => $gradeables,
            "student_full" => $student_full
        ]);
    }
}
