<?php

namespace app\views;

use app\views\AbstractView;
use app\models\User;
use app\models\AutogradingStatusController;

class AutogradingStatusView extends AbstractView {
    public function displayPage($progress) {
        return $this->core->getOutput()->renderTwigTemplate("AutogradingStatus.twig"); 
    }

    public function renderTable($status_json) {
        
    }
}
