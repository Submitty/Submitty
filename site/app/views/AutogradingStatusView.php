<?php

namespace app\views;

use app\views\AbstractView;

class AutogradingStatusView extends AbstractView {
    public function displayPage(array $progress) {
        $this->core->getOutput()->addInternalJs("autograding-status.js");
        $this->core->getOutput()->addInternalCss("autograding-status.css");
        $this->core->getOutput()->addInternalCss('table.css');
        $this->core->getOutput()->addBreadcrumb("Autograding Status");
        return $this->core->getOutput()->renderTwigTemplate("AutogradingStatus.twig", [
            "progress" => $progress
        ]);
    }
}
