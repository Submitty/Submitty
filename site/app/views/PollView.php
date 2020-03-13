<?php

namespace app\views;

use app\models\User;

class PollView extends AbstractView {

    public function showPolls() {
        return $this->core->getOutput()->renderTwigTemplate("PollPage.twig", [
            'csrf_token' => $this->core->getCsrfToken()
          ]);
    }
}