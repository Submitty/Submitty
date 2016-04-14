<?php

namespace app\controllers;

use app\libraries\Output;
use app\models\User;

class AdminController implements IController {
    public function run() {
        if (!User::accessAdmin()) {
            Output::showError("This account cannot access admin pages");
        }
    }
}