<?php

namespace app\views;

use app\libraries\Access;

class AuthenticationView extends AbstractView {
    public function loginForm($old = null, $isSaml = false) {
        if (!isset($old)) {
            $old = urlencode($this->core->buildUrl(['home']));
        }
        $this->core->getOutput()->addInternalCss("input.css");
        $this->core->getOutput()->addInternalCss("links.css");
        $this->core->getOutput()->addInternalCss("authentication.css");
        $this->core->getOutput()->enableMobileViewport();
        return $this->core->getOutput()->renderTwigTemplate("Authentication.twig", [
            "login_url" => $this->core->buildUrl(['authentication', 'check_login']) . '?' . http_build_query(['old' => $old]),
            "is_saml" => $isSaml,
            "saml_url" => $this->core->buildUrl(['authentication', 'saml_start']) . '?' . http_build_query(['old' => $old]),
            "saml_name" => $this->core->getConfig()->getSamlOptions()['name']
        ]);
    }

    public function userSelection(array $users) {
        $this->core->getOutput()->addInternalCss("user-select.css");
        return $this->core->getOutput()->renderTwigTemplate("UserSelection.twig", [
            "users" => $users,
            "access_levels" => Access::ACCESS_LEVELS,
            "login_url" => $this->core->buildUrl(['authentication', 'check_login'])
        ]);
    }
}
