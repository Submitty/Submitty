<?php

namespace app\views;

class AuthenticationView extends AbstractView {
    public function loginForm() {
        $old = $_REQUEST['old'] ?? [];
        return $this->core->getOutput()->renderTwigTemplate("Authentication.twig", [
            "site_url" => $this->core->getConfig()->getSiteUrl(),
            "old" => $old
        ]);
    }
}