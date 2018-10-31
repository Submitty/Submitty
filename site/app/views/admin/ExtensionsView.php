<?php

namespace app\views\admin;

use app\views\AbstractView;
use app\libraries\Utils;

class ExtensionsView extends AbstractView {
    public function displayExtensions($gradeable_ids) {
        $students = $this->core->getQueries()->getAllUsers();
        $student_full = Utils::getAutoFillData($students);

        return $this->core->getOutput()->renderTwigTemplate("admin/Extensions.twig", [
            "gradeable_ids" => $gradeable_ids,
            "student_full" => $student_full
        ]);
    }
}
