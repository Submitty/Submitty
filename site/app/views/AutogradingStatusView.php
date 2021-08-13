<?php

namespace app\views;

use app\views\AbstractView;
use app\models\User;
use app\models\AutogradingStatusController;

class AutogradingStatusView extends AbstractView {
    public function displayPage($progress, $courses) {
        $this->core->getOutput()->addInternalJs("autograding-status.js");
        $this->core->getOutput()->addInternalCss("autograding-status.css");
        $this->core->getOutput()->addInternalCss('table.css');
        return $this->core->getOutput()->renderTwigTemplate("AutogradingStatus.twig", [
            "progress" => $progress,
            "time" => date("H:i:s", time()),
            "courses" => $courses
        ]); 
    }
}
