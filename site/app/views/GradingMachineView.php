<?php

namespace app\views;

use app\views\AbstractView;
use app\models\User;
use app\models\GradingMachineController;

class GradingMachineView extends AbstractView {
    public function displayPage($progress){
        return $this->core->getOutput()->renderTwigTemplate("GradingMachine.twig", [
            "progress" => $progress
        ]); 
    }
}
