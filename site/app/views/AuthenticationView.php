<?php

namespace app\views;

use app\libraries\FileUtils;

class AuthenticationView extends AbstractView {
    public function loginForm($old = null) {
        if (!isset($old)) {
            $old = urlencode($this->core->buildUrl(['home']));
        }
        $this->core->getOutput()->addInternalCss("input.css");
        $this->core->getOutput()->addInternalCss("links.css");
        $this->core->getOutput()->addInternalCss("authentication.css");
        $this->core->getOutput()->enableMobileViewport();

        $login_content = "# Login";
        $path = FileUtils::joinPaths($this->core->getConfig()->getConfigPath(), "login.md");
        if (file_exists($path) && is_readable($path)) {
            $login_content = file_get_contents($path);
        }

        return $this->core->getOutput()->renderTwigTemplate("Authentication.twig", [
            "login_url" => $this->core->buildUrl(['authentication', 'check_login']) . '?' . http_build_query(['old' => $old]),
            "login_content" => $login_content
        ]);
    }
}
