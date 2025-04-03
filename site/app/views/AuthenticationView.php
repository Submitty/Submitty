<?php

namespace app\views;

use app\authentication\DatabaseAuthentication;
use app\libraries\Access;
use app\libraries\FileUtils;

class AuthenticationView extends AbstractView {
    public function loginForm($old = null, $isSaml = false) {
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

        $new_account_text = "New to Submitty? Sign up here.";
        $path = FileUtils::joinPaths($this->core->getConfig()->getConfigPath(), "new_account.md");
        if (file_exists($path) && is_readable($path)) {
            $new_account_text = file_get_contents($path);
        }

        return $this->core->getOutput()->renderTwigTemplate("Authentication.twig", [
            "login_url" => $this->core->buildUrl(['authentication', 'check_login']) . '?' . http_build_query(['old' => $old]),
            "is_saml" => $isSaml,
            "saml_url" => $this->core->buildUrl(['authentication', 'saml_start']) . '?' . http_build_query(['old' => $old]),
            "saml_name" => $this->core->getConfig()->getSamlOptions()['name'],
            "login_content" => $login_content,
            "user_create_account" => $this->core->getConfig()->isUserCreateAccount(),
            "is_database_auth" => $this->core->getAuthentication() instanceof DatabaseAuthentication,
            "new_account_url" => $this->core->buildUrl(['authentication', 'create_account']),
            "new_account_text" => "New to Submitty? Sign up now!"
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

    /**
     * @param array<mixed> $content
     */
    public function signupForm(array $content): string {
        $this->core->getOutput()->addInternalCss("input.css");
        $this->core->getOutput()->addInternalCss("links.css");
        $this->core->getOutput()->addInternalJs("authentication.js");
        $this->core->getOutput()->addInternalCss("authentication.css");
        $this->core->getOutput()->enableMobileViewport();
        $signup_content = "# Sign Up";
        $path = FileUtils::joinPaths($this->core->getConfig()->getConfigPath(), "signup.md");
        if (file_exists($path) && is_readable($path)) {
            $signup_content = file_get_contents($path);
        }
        return $this->core->getOutput()->renderTwigTemplate("CreateNewAccount.twig", [
            "signup_url" => $this->core->buildUrl(['authentication', 'self_add_user']),
            "signup_content" => $signup_content,
            "requirements" => $content
        ]);
    }

    public function verificationForm(): string {
        $this->core->getOutput()->addInternalCss("input.css");
        $this->core->getOutput()->addInternalCss("links.css");
        $this->core->getOutput()->addInternalCss("authentication.css");
        $this->core->getOutput()->enableMobileViewport();
        return $this->core->getOutput()->renderTwigTemplate("VerifyEmailForm.twig", [
            'verify_email_url' => $this->core->buildUrl(['authentication', 'verify_email']),
            'resend_email_url' => $this->core->buildUrl(['authentication', 'resend_email']),
        ]);
    }
}
