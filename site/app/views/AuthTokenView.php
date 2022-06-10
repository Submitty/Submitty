<?php

namespace app\views;

class AuthTokenView extends AbstractView {
    public function showAuthTokenPage(bool $is_faculty, array $tokens, $new_vcs_token = null, $new_vcs_token_val = null, $new_api_token = null) {
        $this->core->getOutput()->addInternalCss('auth-tokens.css');
        $this->core->getOutput()->addInternalModuleJs('auth-tokens.js');
        $this->core->getOutput()->addBreadcrumb("Authentication Tokens");

        $params = [
            "vcs_tokens" => $tokens,
            "csrf_token" => $this->core->getCsrfToken(),
            "current_time" => $this->core->getDateTimeNow(),
            "is_faculty" => $is_faculty
        ];
        if ($new_vcs_token !== null && $new_vcs_token_val !== null) {
            $params["new_vcs_token"] = $new_vcs_token;
            $params["new_vcs_token_val"] = $new_vcs_token_val;
        }
        if ($new_api_token !== null) {
            $params["new_api_token"] = $new_api_token;
        }

        return $this->output->renderTwigTemplate('AuthTokens.twig', $params);
    }
}
