<?php

namespace app\views;

class AuthenticationView extends AbstractView {
    public function loginForm($old = null) {
        if (!isset($old)) {
            $old = urlencode($this->core->buildNewUrl(['home']));
        }
        $this->core->getOutput()->addInternalCss("input.css");
        $this->core->getOutput()->addInternalCss("links.css");
        $this->core->getOutput()->addInternalCss("authentication.css");
        return $this->core->getOutput()->renderTwigTemplate("Authentication.twig", [
            "login_url" => $this->core->buildNewUrl(['authentication', 'check_login']) . '?' . http_build_query(['old' => $old])
        ]);
    }
}