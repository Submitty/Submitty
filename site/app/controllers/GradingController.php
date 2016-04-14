<?php

namespace app\controllers;

use app\libraries\Output;
use app\models\User;

class GradingController implements IController{
    public function run() {
        if (!User::accessGrading()) {
            Output::showError("This account is not authorized to view grading section");
        }
    }
}