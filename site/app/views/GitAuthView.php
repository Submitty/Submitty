<?php

namespace app\views;

class GitAuthView extends AbstractView {
    public function showGitAuthPage(array $tokens, $new_token = null, $new_token_val = null) {
        $this->core->getOutput()->addInternalCss('git-auth-tokens.css');
        $this->core->getOutput()->addInternalModuleJs('git-auth-tokens.js');

        $params = [
            "tokens" => $tokens,
            "csrf_token" => $this->core->getCsrfToken(),
            "current_time" => $this->core->getDateTimeNow()
        ];
        if ($new_token !== null && $new_token_val !== null) {
            $params["new_token"] = $new_token;
            $params["new_token_val"] = $new_token_val;
        }

        return $this->output->renderTwigTemplate('GitAuthTokens.twig', $params);
    }
}
