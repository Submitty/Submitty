<?php

namespace app\views;

class AuthenticationView extends AbstractView {
    public function loginForm($old) {
        return $this->core->getOutput()->renderTwigTemplate("Authentication.twig", [
            "login_url" => $this->core->buildNewUrl(['authentication', 'check_login']),
            "old" => $old
        ]);
    }
}