<?php

namespace app\views\admin;

use app\views\AbstractView;
use app\libraries\Utils;

class GradeOverrideView extends AbstractView {
    public function displayOverridenGrades() {

        return $this->core->getOutput()->renderTwigTemplate("admin/GradeOverride.twig",[] );
    }
}