<?php

namespace app\views;

class ApiTokenView extends AbstractView {
    public function showApiToken($token = null) {
        $this->core->getOutput()->addInternalCss("api-token.css");

        return $this->core->getOutput()->renderTwigTemplate("ApiToken.twig", [
            "token" => $token,
            "csrf_token" => $this->core->getCsrfToken()
        ]);
    }
}
