<?php

namespace app\views;

class AuthTokenView extends AbstractView {
    public function showAuthTokenPage(array $tokens, $new_token = null, $new_token_val = null) {
        $this->core->getOutput()->addInternalCss('auth-tokens.css');
        $this->core->getOutput()->addInternalModuleJs('auth-tokens.js');
        $this->core->getOutput()->addBreadcrumb("Authentication Tokens");

        $params = [
            "tokens" => $tokens,
            "csrf_token" => $this->core->getCsrfToken(),
            "current_time" => $this->core->getDateTimeNow()
        ];
        if ($new_token !== null && $new_token_val !== null) {
            $params["new_token"] = $new_token;
            $params["new_token_val"] = $new_token_val;
        }

        return $this->output->renderTwigTemplate('AuthTokens.twig', $params);
    }
}
